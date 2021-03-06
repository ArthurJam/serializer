<?php

/*
 * Copyright 2016 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\Serializer;

use JMS\Serializer\Construction\ObjectConstructorInterface;
use JMS\Serializer\EventDispatcher\EventDispatcher;
use JMS\Serializer\EventDispatcher\EventDispatcherInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\EventDispatcher\PreDeserializeEvent;
use JMS\Serializer\EventDispatcher\PreSerializeEvent;
use JMS\Serializer\Exception\CircularReferenceDetectedException;
use JMS\Serializer\Exception\ExcludedClassException;
use JMS\Serializer\Exception\ExpressionLanguageRequiredException;
use JMS\Serializer\Exception\InvalidArgumentException;
use JMS\Serializer\Exception\RuntimeException;
use JMS\Serializer\Exclusion\ExpressionLanguageExclusionStrategy;
use JMS\Serializer\Expression\ExpressionEvaluatorInterface;
use JMS\Serializer\Handler\HandlerRegistryInterface;
use JMS\Serializer\Metadata\ClassMetadata;
use Metadata\MetadataFactoryInterface;

/**
 * Handles traversal along the object graph.
 *
 * This class handles traversal along the graph, and calls different methods
 * on visitors, or custom handlers to process its nodes.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
final class SerializationGraphNavigator implements GraphNavigatorInterface
{
    /**
     * @var ExpressionLanguageExclusionStrategy
     */
    private $expressionExclusionStrategy;

    private $dispatcher;
    private $metadataFactory;
    private $handlerRegistry;

    public function __construct(
        MetadataFactoryInterface $metadataFactory,
        HandlerRegistryInterface $handlerRegistry,
        EventDispatcherInterface $dispatcher = null,
        ExpressionEvaluatorInterface $expressionEvaluator = null
    )
    {
        $this->dispatcher = $dispatcher ?: new EventDispatcher();
        $this->metadataFactory = $metadataFactory;
        $this->handlerRegistry = $handlerRegistry;
        if ($expressionEvaluator) {
            $this->expressionExclusionStrategy = new ExpressionLanguageExclusionStrategy($expressionEvaluator);
        }
    }

    /**
     * Called for each node of the graph that is being traversed.
     *
     * @param mixed $data the data depends on the direction, and type of visitor
     * @param null|array $type array has the format ["name" => string, "params" => array]
     * @param Context|SerializationContext $context
     * @return mixed the return value depends on the direction, and type of visitor
     */
    public function accept($data, array $type = null, Context $context)
    {
        $visitor = $context->getVisitor();

        // If the type was not given, we infer the most specific type from the
        // input data in serialization mode.
        if (null === $type) {

            $typeName = \gettype($data);
            if ('object' === $typeName) {
                $typeName = \get_class($data);
            }

            $type = array('name' => $typeName, 'params' => array());
        }
        // If the data is null, we have to force the type to null regardless of the input in order to
        // guarantee correct handling of null values, and not have any internal auto-casting behavior.
        else if (null === $data) {
            $type = array('name' => 'NULL', 'params' => array());
        }
        // Sometimes data can convey null but is not of a null type.
        // Visitors can have the power to add this custom null evaluation
        if ($visitor instanceof NullAwareVisitorInterface && $visitor->isNull($data) === true) {
            $type = array('name' => 'NULL', 'params' => array());
        }

        switch ($type['name']) {
            case 'NULL':
                return $visitor->visitNull($data, $type);

            case 'string':
                return $visitor->visitString($data, $type);

            case 'int':
            case 'integer':
                return $visitor->visitInteger($data, $type);

            case 'bool':
            case 'boolean':
                return $visitor->visitBoolean($data, $type);

            case 'double':
            case 'float':
                return $visitor->visitDouble($data, $type);

            case 'array':
                return $visitor->visitArray($data, $type);

            case 'resource':
                $msg = 'Resources are not supported in serialized data.';
                if (null !== $path = $context->getPath()) {
                    $msg .= ' Path: ' . $path;
                }

                throw new RuntimeException($msg);

            default:

                if (null !== $data) {
                    if ($context->isVisiting($data)) {
                        throw new CircularReferenceDetectedException();
                    }
                    $context->startVisiting($data);
                }

                // If we're serializing a polymorphic type, then we'll be interested in the
                // metadata for the actual type of the object, not the base class.
                if (class_exists($type['name'], false) || interface_exists($type['name'], false)) {
                    if (is_subclass_of($data, $type['name'], false)) {
                        $type = array('name' => \get_class($data), 'params' => array());
                    }
                }

                $format = $context->getFormat();

                // Trigger pre-serialization callbacks, and listeners if they exist.
                // Dispatch pre-serialization event before handling data to have ability change type in listener
                if ($this->dispatcher->hasListeners('serializer.pre_serialize', $type['name'], $format)) {
                    $this->dispatcher->dispatch('serializer.pre_serialize', $type['name'], $format, $event = new PreSerializeEvent($context, $data, $type));
                    $type = $event->getType();
                }

                // First, try whether a custom handler exists for the given type. This is done
                // before loading metadata because the type name might not be a class, but
                // could also simply be an artifical type.
                if (null !== $handler = $this->handlerRegistry->getHandler(GraphNavigatorInterface::DIRECTION_SERIALIZATION, $type['name'], $format)) {
                    $rs = \call_user_func($handler, $visitor, $data, $type, $context);
                    $context->stopVisiting($data);

                    return $rs;
                }

                $exclusionStrategy = $context->getExclusionStrategy();

                /** @var $metadata ClassMetadata */
                $metadata = $this->metadataFactory->getMetadataForClass($type['name']);

                if ($metadata->usingExpression && $this->expressionExclusionStrategy === null) {
                    throw new ExpressionLanguageRequiredException("To use conditional exclude/expose in {$metadata->name} you must configure the expression language.");
                }

                if ($exclusionStrategy->shouldSkipClass($metadata, $context)) {
                    $context->stopVisiting($data);

                    throw new ExcludedClassException();
                }

                $context->pushClassMetadata($metadata);

                foreach ($metadata->preSerializeMethods as $method) {
                    $method->invoke($data);
                }

                $visitor->startVisitingObject($metadata, $data, $type, $context);
                foreach ($metadata->propertyMetadata as $propertyMetadata) {
                    if ($exclusionStrategy->shouldSkipProperty($propertyMetadata, $context)) {
                        continue;
                    }

                    if (null !== $this->expressionExclusionStrategy && $this->expressionExclusionStrategy->shouldSkipProperty($propertyMetadata, $context)) {
                        continue;
                    }

                    $context->pushPropertyMetadata($propertyMetadata);
                    $visitor->visitProperty($propertyMetadata, $data);
                    $context->popPropertyMetadata();
                }

                $this->afterVisitingObject($metadata, $data, $type, $context, $format);

                return $visitor->endVisitingObject($metadata, $data, $type);
        }
    }

    private function afterVisitingObject(ClassMetadata $metadata, $object, array $type, Context $context, $format)
    {
        $context->stopVisiting($object);
        $context->popClassMetadata();

        foreach ($metadata->postSerializeMethods as $method) {
            $method->invoke($object);
        }

        if ($this->dispatcher->hasListeners('serializer.post_serialize', $metadata->name, $format)) {
            $this->dispatcher->dispatch('serializer.post_serialize', $metadata->name, $format, new ObjectEvent($context, $object, $type));
        }
    }
}
