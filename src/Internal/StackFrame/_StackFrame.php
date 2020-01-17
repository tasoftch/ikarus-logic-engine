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

use Ikarus\Logic\Internal\_SignalServer;
use Ikarus\Logic\ValueProvider\ValueProviderInterface;

class _StackFrame
{
    /** @var null|_StackFrame */
    public $parentFrame;
    /** @var null|_StackFrame */
    public $nextFrame;

    public $updatedComponents = [];
    public $updatedNodes = [];

    /** @var ValueProviderInterface */
    public $valueProvider;

    /** @var _RenderCycle[] */
    private $renderCycles = [];
    /** @var _SignalServer */
    protected $valuesServer;

    public $cachedOutputValues;
    public $cachedExposedValues;
    public $cachedExposedSignals;
    public $cachedInputValues;

    public function __construct()
    {
        $this->valuesServer = new _SignalServer();
    }

    public function pushCycle($nodeID, $nodeAttrs, $componentName, $requestedSocket, $triggeredSocket, callable $inputValueProvider, callable $signalForwarder = NULL) {
        $cycle = new _RenderCycle();
        $cycle->nodeIdentifier = $nodeID;
        $cycle->nodeAttributes = $nodeAttrs;
        $cycle->requestedSocketName = $requestedSocket;
        $cycle->nodeComponentName = $componentName;
        $cycle->triggeredSocketName = $triggeredSocket;
        $cycle->inputValuesProvider = $inputValueProvider;
        $cycle->signalForwarder = $signalForwarder;

        $this->renderCycles[] = $cycle;
        $this->updateValuesServer();
    }

    public function getCycle(): ?_RenderCycle {
        return end($this->renderCycles) ?: NULL;
    }

    public function popCycle() {
        array_pop($this->renderCycles);
        $this->updateValuesServer();
    }

    protected function putOutputValue($socketName, $value, $nodeID) {
        $this->cachedOutputValues["$nodeID:$socketName"] = $value;
    }

    protected function putExposedValue($socketName, $value, $nodeID) {
        $this->cachedExposedValues[$nodeID][$socketName] = $value;
    }

    protected function putExposedSignal($socketName, $nodeID) {
        $this->cachedExposedSignals[] = [$nodeID, $socketName];
    }

    protected function putInputValue($socketName, $value, $nodeID) {
        $this->cachedInputValues["$nodeID:$socketName"] = $value;
    }

    protected function fetchInputValue($socketName, &$found = NULL) {
        if($cycle = $this->getCycle()) {
            $nodeID = $cycle->nodeIdentifier;
            if(!isset($this->cachedInputValues["$nodeID:$socketName"])) {
                $this->putInputValue($socketName, ($cycle->inputValuesProvider)($socketName), $nodeID);
            }
            $found = true;
            return $this->cachedInputValues["$nodeID:$socketName"];
        }
        $found = false;
        return NULL;
    }

    protected function triggerSignal($socketName, $nodeID) {
        if($cycle = $this->getCycle()) {
            call_user_func( $cycle->signalForwarder, $socketName, $nodeID);
        }
    }

    public function updateValuesServer() {
        if($cycle = $this->getCycle()) {
            $nodeID = $cycle->nodeIdentifier;

            $this->valuesServer->outputValues = function($socketName, $value) use ($nodeID) {
                $this->putOutputValue($socketName, $value, $nodeID);
            };
            $this->valuesServer->exposedValues = function($socketName, $value) use ($nodeID) {
                $this->putExposedValue($socketName, $value, $nodeID);
            };
            $this->valuesServer->inputValues = function($socketName) {
                return $this->fetchInputValue($socketName);
            };
            if($cycle->signalForwarder) {
                $this->valuesServer->signalForwarder = function($socketName) use ($nodeID) {
                    $this->triggerSignal($socketName, $nodeID);
                };
                $this->valuesServer->exposedSignals = function($socketName) use ($nodeID) {
                    $this->putExposedSignal($socketName, $nodeID);
                };
            } else {
                $this->valuesServer->signalForwarder = function($sn){trigger_error("No signal server available for socket $sn", E_USER_NOTICE);};
            }

        } else {
            // Should only happen after last node was updated or a manual interaction occured.
            $this->valuesServer->inputValues =
            $this->valuesServer->outputValues =
            $this->valuesServer->exposedValues =
                function(){};

            $this->valuesServer->signalForwarder =
                function($sn){trigger_error("No signal server available for socket $sn", E_USER_NOTICE);};
        }
    }

    public function hasExposedValue($socketName, $nodeIdentifier = NULL) {
        if(!$nodeIdentifier)
            $nodeIdentifier = $this->getCycle()->nodeIdentifier;
        return isset($this->cachedExposedValues[$nodeIdentifier][$socketName]);
    }

    public function getExposedValue($socketName, $nodeIdentifier = NULL) {
        if(!$nodeIdentifier)
            $nodeIdentifier = $this->getCycle()->nodeIdentifier;
        return $this->cachedExposedValues[$nodeIdentifier][$socketName] ?? NULL;
    }

    public function hasOutputValue($socketName, $nodeIdentifier = NULL) {
        if(!$nodeIdentifier)
            $nodeIdentifier = $this->getCycle()->nodeIdentifier;
        return isset($this->cachedOutputValues["$nodeIdentifier:$socketName"]);
    }

    public function getOutputValue($socketName, $nodeIdentifier = NULL) {
        if(!$nodeIdentifier)
            $nodeIdentifier = $this->getCycle()->nodeIdentifier;
        return $this->cachedOutputValues["$nodeIdentifier:$socketName"] ?? NULL;
    }

    /**
     * @return _SignalServer
     */
    public function getValuesServer(): _SignalServer
    {
        return $this->valuesServer;
    }
}