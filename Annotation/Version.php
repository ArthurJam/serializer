<?php

/*
 * Copyright 2011 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\SerializerBundle\Annotation;

use JMS\SerializerBundle\Exception\RuntimeException;

abstract class Version
{
    private $version;

    protected function setVersion($version)
    {
        if (!is_scalar($version)) {
            throw new RuntimeException('$version must be a scalar.');
        }

        $this->version = $version;
    }

    public function getVersion()
    {
        return $this->version;
    }
}