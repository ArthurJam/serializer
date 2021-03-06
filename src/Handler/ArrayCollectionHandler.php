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

namespace JMS\Serializer\Handler;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\DeserializationVisitorInterface;
use JMS\Serializer\GraphNavigatorInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializationVisitorInterface;

final class ArrayCollectionHandler implements SubscribingHandlerInterface
{
    /**
     * @var bool
     */
    private $initializeExcluded = true;

    public function __construct($initializeExcluded = true)
    {
        $this->initializeExcluded = $initializeExcluded;
    }

    public static function getSubscribingMethods()
    {
        $methods = array();
        $formats = array('json', 'xml', 'yml');
        $collectionTypes = array(
            'ArrayCollection',
            'Doctrine\Common\Collections\ArrayCollection',
            'Doctrine\ORM\PersistentCollection',
            'Doctrine\ODM\MongoDB\PersistentCollection',
            'Doctrine\ODM\PHPCR\PersistentCollection',
        );

        foreach ($collectionTypes as $type) {
            foreach ($formats as $format) {
                $methods[] = array(
                    'direction' => GraphNavigatorInterface::DIRECTION_SERIALIZATION,
                    'type' => $type,
                    'format' => $format,
                    'method' => 'serializeCollection',
                );

                $methods[] = array(
                    'direction' => GraphNavigatorInterface::DIRECTION_DESERIALIZATION,
                    'type' => $type,
                    'format' => $format,
                    'method' => 'deserializeCollection',
                );
            }
        }

        return $methods;
    }

    public function serializeCollection(SerializationVisitorInterface $visitor, Collection $collection, array $type, SerializationContext $context)
    {
        // We change the base type, and pass through possible parameters.
        $type['name'] = 'array';

        $context->stopVisiting($collection);

        if ($this->initializeExcluded === false) {
            $exclusionStrategy = $context->getExclusionStrategy();
            if ($exclusionStrategy !== null && $exclusionStrategy->shouldSkipClass($context->getMetadataFactory()->getMetadataForClass(\get_class($collection)), $context)) {
                $context->startVisiting($collection);

                return $visitor->visitArray([], $type, $context);
            }
        }
        $result = $visitor->visitArray($collection->toArray(), $type);

        $context->startVisiting($collection);
        return $result;
    }

    public function deserializeCollection(DeserializationVisitorInterface $visitor, $data, array $type, DeserializationContext $context)
    {
        // See above.
        $type['name'] = 'array';

        return new ArrayCollection($visitor->visitArray($data, $type));
    }
}
