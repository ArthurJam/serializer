<?php

/*
 * Copyright 2013 Johannes M. Schmitt <schmittjoh@gmail.com>
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

namespace JMS\Serializer\Tests\Serializer;

use Doctrine\Common\Annotations\AnnotationReader;
use JMS\Serializer\Accessor\DefaultAccessorStrategy;
use JMS\Serializer\Construction\UnserializeObjectConstructor;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\Handler\HandlerRegistry;
use JMS\Serializer\JsonDeserializationVisitor;
use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\Metadata\Driver\AnnotationDriver;
use JMS\Serializer\Naming\CamelCaseNamingStrategy;
use JMS\Serializer\Naming\IdenticalPropertyNamingStrategy;
use JMS\Serializer\Naming\SerializedNameAnnotationStrategy;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\Serializer;
use JMS\Serializer\VisitorFactory\JsonDeserializationVisitorFactory;
use JMS\Serializer\VisitorFactory\JsonSerializationVisitorFactory;
use Metadata\MetadataFactory;

class SerializationContextFactoryTest extends \PHPUnit\Framework\TestCase
{
    protected $serializer;
    protected $metadataFactory;
    protected $handlerRegistry;
    protected $unserializeObjectConstructor;
    protected $serializationVisitors;
    protected $deserializationVisitors;

    public function setUp()
    {
        parent::setUp();

        $namingStrategy = new SerializedNameAnnotationStrategy(new CamelCaseNamingStrategy());
        $this->metadataFactory = new MetadataFactory(new AnnotationDriver(new AnnotationReader(), $namingStrategy));
        $this->handlerRegistry = new HandlerRegistry();
        $this->unserializeObjectConstructor = new UnserializeObjectConstructor();

        $this->serializationVisitors = array('json' => new JsonSerializationVisitorFactory());
        $this->deserializationVisitors = array('json' => new JsonDeserializationVisitorFactory());
    }

    public function testSerializeUseProvidedSerializationContext()
    {
        $contextFactoryMock = $this->getMockForAbstractClass('JMS\\Serializer\\ContextFactory\\SerializationContextFactoryInterface');
        $context = new SerializationContext();
        $context->setSerializeNull(true);

        $contextFactoryMock
            ->expects($this->once())
            ->method('createSerializationContext')
            ->will($this->returnValue($context));

        $serializer = new Serializer(
            $this->metadataFactory,
            $this->handlerRegistry,
            $this->unserializeObjectConstructor,
            $this->serializationVisitors,
            $this->deserializationVisitors,
            new DefaultAccessorStrategy(),
            null,
            null,
            null,
            $contextFactoryMock
        );

        $result = $serializer->serialize(array('value' => null), 'json');

        $this->assertEquals('{"value":null}', $result);
    }

    public function testDeserializeUseProvidedDeserializationContext()
    {
        $contextFactoryMock = $this->getMockForAbstractClass('JMS\\Serializer\\ContextFactory\\DeserializationContextFactoryInterface');
        $context = new DeserializationContext();

        $contextFactoryMock
            ->expects($this->once())
            ->method('createDeserializationContext')
            ->will($this->returnValue($context));

        $serializer = new Serializer(
            $this->metadataFactory,
            $this->handlerRegistry,
            $this->unserializeObjectConstructor,
            $this->serializationVisitors,
            $this->deserializationVisitors,
            new DefaultAccessorStrategy(),
            null,
            null,
            null,
            null,
            $contextFactoryMock
        );

        $result = $serializer->deserialize('{"value":null}', 'array', 'json');

        $this->assertEquals(array('value' => null), $result);
    }

    public function testToArrayUseProvidedSerializationContext()
    {
        $contextFactoryMock = $this->getMockForAbstractClass('JMS\\Serializer\\ContextFactory\\SerializationContextFactoryInterface');
        $context = new SerializationContext();
        $context->setSerializeNull(true);

        $contextFactoryMock
            ->expects($this->once())
            ->method('createSerializationContext')
            ->will($this->returnValue($context));

        $serializer = new Serializer(
            $this->metadataFactory,
            $this->handlerRegistry,
            $this->unserializeObjectConstructor,
            $this->serializationVisitors,
            $this->deserializationVisitors,
            new DefaultAccessorStrategy(),
            null,
            null,
            null,
            $contextFactoryMock
        );

        $result = $serializer->toArray(array('value' => null));

        $this->assertEquals(array('value' => null), $result);
    }

    public function testFromArrayUseProvidedDeserializationContext()
    {
        $contextFactoryMock = $this->getMockForAbstractClass('JMS\\Serializer\\ContextFactory\\DeserializationContextFactoryInterface');
        $context = new DeserializationContext();

        $contextFactoryMock
            ->expects($this->once())
            ->method('createDeserializationContext')
            ->will($this->returnValue($context));

        $serializer = new Serializer(
            $this->metadataFactory,
            $this->handlerRegistry,
            $this->unserializeObjectConstructor,
            $this->serializationVisitors,
            $this->deserializationVisitors,
            new DefaultAccessorStrategy(),
            null,
            null,
            null,
            null,
            $contextFactoryMock
        );

        $result = $serializer->fromArray(array('value' => null), 'array');

        $this->assertEquals(array('value' => null), $result);
    }
}
