<?php
/**
 * BSD 3-Clause License
 *
 * Copyright (c) 2019, TASoft Applications
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 *  Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 *  Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace Ikarus\Logic\Internal\StackFrame;


use Ikarus\Logic\ValueProvider\ValueProviderInterface;

class _PermeableStackFrame extends _StackFrame
{
    protected function putOutputValue($socketName, $value, $nodeID)
    {
        parent::putOutputValue($socketName, $value, $nodeID);
        if($this->parentFrame)
            $this->parentFrame->putOutputValue($socketName, $value, $nodeID);
    }

    protected function putExposedValue($socketName, $value, $nodeID)
    {
        parent::putExposedValue($socketName, $value, $nodeID);
        if($this->parentFrame)
            $this->parentFrame->putExposedValue($socketName, $value, $nodeID);
    }

    protected function putExposedSignal($socketName, $nodeID)
    {
        parent::putExposedSignal($socketName, $nodeID);
        if($this->parentFrame)
            $this->parentFrame->putExposedSignal($socketName, $nodeID);
    }


    protected function putInputValue($socketName, $value, $nodeID)
    {
        parent::putInputValue($socketName, $value, $nodeID);
        if($this->parentFrame)
            $this->parentFrame->putInputValue($socketName, $value, $nodeID);
    }

    protected function fetchInputValue($socketName, &$found = NULL)
    {
        $value = parent::fetchInputValue($socketName, $found);
        if(!$found && $this->parentFrame) {
            $value = $this->parentFrame->fetchInputValue($socketName, $found);
        }
        return $value;
    }

    public function hasOutputValue($socketName, $nodeIdentifier = NULL)
    {
        if(!parent::hasOutputValue($socketName, $nodeIdentifier)) {
            if($this->parentFrame)
                return $this->parentFrame->hasOutputValue($socketName, $nodeIdentifier);
            return false;
        }
        return true;
    }

    public function hasExposedValue($socketName, $nodeIdentifier = NULL)
    {
        if(!parent::hasExposedValue($socketName, $nodeIdentifier)) {
            if($this->parentFrame)
                return $this->parentFrame->hasExposedValue($socketName, $nodeIdentifier);
            return false;
        }
        return true;
    }

    public function getExposedValue($socketName, $nodeIdentifier = NULL)
    {
        if(!$nodeIdentifier)
            $nodeIdentifier = $this->getCycle()->nodeIdentifier;

        $f = $this;
        do {
            if(isset($f->cachedExposedValues["$nodeIdentifier:$socketName"]))
                return $f->cachedExposedValues["$nodeIdentifier:$socketName"];
        } while($f = $f->parentFrame);
        return NULL;
    }

    public function getOutputValue($socketName, $nodeIdentifier = NULL)
    {
        if(!$nodeIdentifier)
            $nodeIdentifier = $this->getCycle()->nodeIdentifier;

        $f = $this;
        do {
            if(isset($f->cachedOutputValues["$nodeIdentifier:$socketName"]))
                return $f->cachedOutputValues["$nodeIdentifier:$socketName"];
        } while($f = $f->parentFrame);
        return NULL;
    }

    public function getValueProvider(): ?ValueProviderInterface
    {
        if($this->valueProvider)
            return $this->valueProvider;

        if($this->parentFrame)
            return $this->parentFrame->getValueProvider();
        return NULL;
    }
}