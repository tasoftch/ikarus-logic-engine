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

namespace Ikarus\Logic;


use Ikarus\Logic\Data\DataInterface;
use Ikarus\Logic\Exception\ImmutableEngineException;
use Ikarus\Logic\Internal\_ExposedSocketResolver;
use Ikarus\Logic\Internal\_RenderCycleCount;
use Ikarus\Logic\Internal\_RuntimeContext;
use Ikarus\Logic\Internal\_StackFrame;
use Ikarus\Logic\Model\Component\ComponentModelInterface;
use Ikarus\Logic\Model\Component\Socket\AbstractSocketComponent;
use Ikarus\Logic\Model\Component\Socket\ExposedSocketComponentInterface;
use Ikarus\Logic\Model\Data\Node\AttributedNodeDataModel;
use Ikarus\Logic\Model\Exception\InvalidReferenceException;
use Ikarus\Logic\Model\Executable\ExecutableExpressionNodeComponentInterface;
use Ikarus\Logic\ValueProvider\ValueProviderInterface;
use Throwable;

class Engine implements EngineInterface
{
    /** @var ComponentModelInterface */
    private $model;
    private $active = false;
    /** @var DataInterface */
    private $dataSource;

    private $_x;
    private $_X;

    /** @var _RuntimeContext */
    private $context;

    /** @var _RenderCycleCount */
    private $renderCycle;

    /**
     * Engine constructor.
     * @param ComponentModelInterface $componentModel
     */
    public function __construct(ComponentModelInterface $componentModel)
    {
        $this->model = $componentModel;
    }

    private function _checkEngineImmutable() {
        if($this->isActive()) {
            $e = new ImmutableEngineException("Can not modify engine while running", 19);
            $e->setEngine($this);
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function bindData(DataInterface $data): bool
    {
        if($this->isActive())
            return false;
        $this->dataSource = $data;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function activate()
    {
        $this->_checkEngineImmutable();
        if(!$this->dataSource)
            throw new InvalidReferenceException("No data source specified", InvalidReferenceException::CODE_SYMBOL_NOT_FOUND);

        $d = $this->dataSource->getData( $this->model );
        $this->_x = $d["x"] ?? NULL;
        $this->_X = $d["X"] ?? NULL;
        if(NULL === $this->_x || NULL === $this->_X) {
            $e = new InvalidReferenceException("Invalid data source", InvalidReferenceException::CODE_INVALID_INSTANCE);
            $e->setProperty($this->dataSource);
            throw $e;
        }

        $this->context = new _RuntimeContext();
        $this->renderCycle = new _RenderCycleCount();
        $this->active = true;
    }

    /**
     * @inheritDoc
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @inheritDoc
     */
    public function terminate()
    {
        $this->context = NULL;
        $this->renderCycle = NULL;

        $this->_x = $this->_X = NULL;
        $this->active = false;
    }

    /**
     * @inheritDoc
     */
    public function beginRenderCycle()
    {
        if($this->isActive()) {
            $this->renderCycle->cycleCount++;
            if($this->renderCycle->cycleCount == 1)
                $this->renderCycleDidStart();
        }
    }

    /**
     * @inheritDoc
     */
    public function endRenderCycle()
    {
        if($this->isActive()) {
            if($this->renderCycle->cycleCount == 1)
                $this->renderCycleWillEnd();

            $this->renderCycle->cycleCount--;
            if($this->renderCycle->cycleCount == 0)
                $this->renderCycle->release();
        }
    }

    /**
     * Implement this method to interact right after a render cycle starts
     */
    protected function renderCycleDidStart() {
    }

    /**
     * Implement this method to interact right before a render cycle ends
     */
    protected function renderCycleWillEnd() {
    }

    /**
     * @param int|string $nodeIdentifier
     * @param string $exposedSocketKey
     * @param ValueProviderInterface|NULL $valueProvider
     * @return mixed|null
     * @throws Throwable
     */
    public function requestValue($nodeIdentifier, string $exposedSocketKey, ValueProviderInterface $valueProvider = NULL)
    {
        if(!$this->isActive()) {
            trigger_error("Engine is not active", E_USER_ERROR);
            return;
        }
        if(_ExposedSocketResolver::getExposedSocket($nodeIdentifier, $exposedSocketKey, $this->_x, $type, $compName)) {
            try {
                $this->beginRenderCycle();

                $sf = new _StackFrame();
                $sf->valueProvider = $valueProvider;
                $this->context->pushStackFrame($sf);

                $this->updateNode($nodeIdentifier, $exposedSocketKey, true);

                return $sf->cachedExposedValues["$nodeIdentifier:$exposedSocketKey"] ?? NULL;
            } catch (Throwable $exception) {
                throw $exception;
            } finally {
                $this->context->popStackFrame();
                $this->endRenderCycle();
            }
        }
        trigger_error("Socket $exposedSocketKey of node $nodeIdentifier does not exist", E_USER_WARNING);
        return NULL;
    }


    private function makeInputValueFetchCallback($nodeIdentifier, ValueProviderInterface $valueProvider = NULL) {
        $nodeInfo = $this->_X["nd"][$nodeIdentifier];
        /** @var ExecutableExpressionNodeComponentInterface $component */
        $component = $this->getModel()->getComponent($nodeInfo["c"]);

        return function($socketName) use ($nodeIdentifier, $component, $valueProvider) {
            // Check, if node has connection
            if($cinfo = $this->_X['i2o']["$nodeIdentifier:$socketName"] ?? NULL) {
                $c = array_shift($cinfo);
                $destNode = $c["dn"];
                $destSock = $c["dk"];

                $this->updateNode($destNode, $destSock);

                $sf = $this->context->getCurrentStackFrame();

                if($sf->hasOutputValue($destSock, $destNode)) {
                    return $sf->getOutputValue($destSock, $destNode);
                } elseif($valueProvider) {
                    return $valueProvider->getValue($destSock, $destNode);
                }
                return NULL;
            }

            $socket = $component->getInputSockets()[$socketName] ?? $component->getOutputSockets()[$socketName] ?? NULL;
            if($socket instanceof ExposedSocketComponentInterface) {
                if($valueProvider)
                    return $valueProvider->getValue($socketName, $nodeIdentifier);
            }

            // If there is no connection, get from node attributes
            $sf = $this->context->getCurrentStackFrame();
            if(isset($sf->getCycle()->nodeAttributes[ AttributedNodeDataModel::ATTRIBUTE_CUSTOM_INPUT_VALUES ] [$socketName])) {
                return $sf->getCycle()->nodeAttributes[ AttributedNodeDataModel::ATTRIBUTE_CUSTOM_INPUT_VALUES ] [$socketName];
            }

            if($socket instanceof AbstractSocketComponent && $socket->hasDefaultValue()) {
                return $socket->getDefaultValue();
            }

            return NULL;
        };
    }

    /**
     * @param $nodeIdentifier
     * @param $socketName
     * @param bool $exposed
     * @throws Throwable
     */
    private function updateNode($nodeIdentifier, $socketName, bool $exposed = false) {
        $nodeInfo = $this->_X["nd"][$nodeIdentifier];

        if($this->context->needsUpdate( $nodeIdentifier, $componentName = $nodeInfo["c"] )) {
            /** @var ExecutableExpressionNodeComponentInterface $component */
            $component = $this->getModel()->getComponent($componentName);

            $sf = $this->context->getCurrentStackFrame();
            $sf->pushCycle(
                $nodeIdentifier,
                $nodeInfo["a"]??NULL,
                $componentName,
                $socketName,
                NULL,
                $this->makeInputValueFetchCallback($nodeIdentifier, $sf->valueProvider)
            );

            try {
                $component->updateNode($this->context->getCurrentStackFrame()->getValuesServer(), $this->context);
                if($exposed && !$sf->hasExposedValue($exposed, $nodeIdentifier)) {
                    // fetch from input if possible
                    if($input = $component->getInputSockets()[$socketName] ?? NULL) {
                        $value = $sf->getValuesServer()->fetchInputValue($socketName);
                        if(NULL !== $value) {
                            $sf->getValuesServer()->exposeValue($socketName, $value);
                            return;
                        }
                    }
                }
            } catch (Throwable $exception) {
                throw $exception;
            } finally {


                $sf->popCycle();
            }
        }
    }


    public function triggerSignal(string $componentName, string $exposedSocketKey, $nodeIdentifier = NULL)
    {

    }

    /**
     * @return ComponentModelInterface
     */
    public function getModel(): ComponentModelInterface
    {
        return $this->model;
    }
}