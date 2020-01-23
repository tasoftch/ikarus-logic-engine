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


use Ikarus\Logic\Component\SceneGatewayComponent;
use Ikarus\Logic\Data\DataInterface;
use Ikarus\Logic\Exception\ImmutableEngineException;
use Ikarus\Logic\Internal\_ExposedSocketResolver;
use Ikarus\Logic\Internal\_RuntimeContext;
use Ikarus\Logic\Internal\StackFrame\_PermeableStackFrame;
use Ikarus\Logic\Internal\StackFrame\_StackFrame;
use Ikarus\Logic\Model\Component\ComponentModelInterface;
use Ikarus\Logic\Model\Component\Socket\AbstractSocketComponent;
use Ikarus\Logic\Model\Component\Socket\ExposedSocketComponentInterface;
use Ikarus\Logic\Model\Exception\InvalidReferenceException;
use Ikarus\Logic\Model\Executable\ExecutableExpressionNodeComponentInterface;
use Ikarus\Logic\Model\Executable\ExecutableSignalTriggerNodeComponentInterface;
use Ikarus\Logic\ValueProvider\ValueProviderInterface;
use RuntimeException;
use Throwable;

class Engine implements EngineInterface
{
    const MAXIMAL_ALLOWED_RECURSIONS = 20;

    /** @var ComponentModelInterface */
    private $model;
    private $active = false;
    /** @var DataInterface */
    private $dataSource;

    private $_x;
    private $_X;

    /** @var _RuntimeContext */
    private $context;

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

        $this->_x = $this->_X = NULL;
        $this->active = false;
    }

    /**
     * @inheritDoc
     */
    public function beginRenderCycle()
    {
        if($this->isActive()) {
            $this->context->pushStackFrame( new _PermeableStackFrame() );
        }
    }

    /**
     * @inheritDoc
     */
    public function endRenderCycle()
    {
        if($this->isActive()) {
            $this->context->popStackFrame();
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
     * This method asks the logic for a value.
     * It will search for the node with $nodeIdentifier and its exposed socket.
     * If an exposed socket matches the passed $exposedSocketKey the engine will update the node and fetch the exposed value.
     * Fetching a value increases the render cycle.
     * So if you want to fetch several values at one time, begin a render cycle before and end it after! (Much better performance).
     *
     * @param string|int $nodeIdentifier
     * @param string $exposedSocketKey
     * @param ValueProviderInterface|null $valueProvider
     * @param null|Throwable $error
     * @return mixed|array
     */
    public function requestValue($nodeIdentifier, string $exposedSocketKey, ValueProviderInterface $valueProvider = NULL, &$error = NULL)
    {
        if(!$this->isActive()) {
            trigger_error("Engine is not active", E_USER_ERROR);
            return NULL;
        }
        if(_ExposedSocketResolver::getExposedSocket($nodeIdentifier, $exposedSocketKey, $this->_x, $type, $compName)) {
            try {
                $this->beginRenderCycle();

                $sf = new _PermeableStackFrame();
                $sf->valueProvider = $valueProvider;
                $this->context->pushStackFrame($sf);

                $this->_updateNode($nodeIdentifier, $exposedSocketKey);

                return $sf->cachedExposedValues[$nodeIdentifier][$exposedSocketKey] ?? NULL;
            } catch (Throwable $exception) {
                $error = $exception;
                return NULL;
            } finally {
                $this->context->popStackFrame();
                $this->endRenderCycle();
            }
        }
        trigger_error("Socket $exposedSocketKey of node $nodeIdentifier does not exist", E_USER_WARNING);
        return NULL;
    }

    /**
     *  If you want all exposed values of a node, use this method instead of requestValue.
     *
     * @param $nodeIdentifier
     * @param ValueProviderInterface $valueProvider
     * @param null|Throwable $error
     * @return array|null
     */
    public function updateNode($nodeIdentifier, ValueProviderInterface $valueProvider, &$error = NULL) {
        if(!$this->isActive()) {
            trigger_error("Engine is not active", E_USER_ERROR);
            return NULL;
        }

        try {
            $this->beginRenderCycle();

            $sf = new _PermeableStackFrame();
            $sf->valueProvider = $valueProvider;
            $this->context->pushStackFrame($sf);

            return $this->_updateNode($nodeIdentifier, NULL);
        } catch (Throwable $exception) {
            $error = $exception;
            return NULL;
        } finally {
            $this->context->popStackFrame();
            $this->endRenderCycle();
        }
    }


    private function makeInputValueFetchCallback($nodeIdentifier, _StackFrame $stackFrame) {
        $nodeInfo = $this->_X["nd"][$nodeIdentifier];
        /** @var ExecutableExpressionNodeComponentInterface $component */
        $component = $this->getModel()->getComponent($nodeInfo["c"]);

        return function($socketName) use ($nodeIdentifier, $component, $stackFrame) {
            // Check, if node has connection
            if($cinfo = $this->_X['i2o']["$nodeIdentifier:$socketName"] ?? NULL) {
                $value = NULL;
                if(!isset($component->getInputSockets()[$socketName])) {
                    $mpl = count($cinfo) > 1 ? true : false;
                } else
                    $mpl = $component->getInputSockets()[$socketName]->allowsMultiple();

                $setValue = function($v) use (&$value, $mpl) {
                    if($mpl)
                        $value[] = $v;
                    else
                        $value = $v;
                };

                do {
                    $c = array_shift($cinfo);

                    $destNode = $c["dn"];
                    $destSock = $c["dk"];

                    if($stackFrame->hasOutputValue($destSock, $destNode)) {
                        $setValue( $stackFrame->getOutputValue($destSock, $destNode) );
                    } else {
                        $this->_updateNode($destNode, $destSock);
                        if($stackFrame->hasOutputValue($destSock, $destNode)) {
                            $setValue( $stackFrame->getOutputValue($destSock, $destNode) );
                        } elseif($vp = $stackFrame->getValueProvider()) {
                            $setValue( $vp->getValue($destSock, $destNode) );
                        }
                    }

                } while($cinfo && $mpl);

                return $value;
            }

            $socket = $component->getInputSockets()[$socketName] ?? $component->getOutputSockets()[$socketName] ?? NULL;
            if($socket instanceof ExposedSocketComponentInterface) {
                if($vp = $stackFrame->getValueProvider())
                    return $vp->getValue($socketName, $nodeIdentifier);
            }

            // If there is no connection, get from node attributes
            $sf = $this->context->getCurrentStackFrame();
            if(isset($sf->getCycle()->nodeAttributes[ $socketName ])) {
                return $sf->getCycle()->nodeAttributes[ $socketName ];
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
     * @throws Throwable
     * @internal
     */
    private function _updateNode($nodeIdentifier, $socketName) {
        $nodeInfo = $this->_X["nd"][$nodeIdentifier];

        if($this->context->needsUpdate( $nodeIdentifier, $componentName = $nodeInfo["c"] )) {
            /** @var ExecutableExpressionNodeComponentInterface $component */
            $component = $this->getModel()->getComponent($componentName);
            if($component instanceof SceneGatewayComponent && $this !== $component->getEngine()) {
                (function($e){$this->engine=$e;})->bindTo($component, SceneGatewayComponent::class)->call($component, $this);
            }

            $sf = $this->context->getCurrentStackFrame();
            $sf->pushCycle(
                $nodeIdentifier,
                $nodeInfo["a"]??NULL,
                $componentName,
                $socketName,
                NULL,
                $this->makeInputValueFetchCallback($nodeIdentifier, $sf)
            );

            try {
                static $recursionCounter = 0;
                $recursionCounter++;

                if($recursionCounter >= self::MAXIMAL_ALLOWED_RECURSIONS) {
                    throw new RuntimeException("Maximal recursion stack depth reached");
                }

                $component->updateNode($this->context->getCurrentStackFrame()->getValuesServer(), $this->context);

                $recursionCounter--;
                return $sf->cachedExposedValues[$nodeIdentifier] ?? NULL;
            } catch (Throwable $exception) {
                throw $exception;
            } finally {
                $sf->popCycle();
            }
        }
        return NULL;
    }

    /**
     * Internal method to determine the next connected nodes
     *
     * @param array $nodeIdentifiers
     * @param $socket
     * @return array
     * @internal
     */
    private function _getNextNodes(array $nodeIdentifiers, $socket): array {
        $next = [];

        foreach($nodeIdentifiers as $identifier) {
            if(isset($this->_X["o2i"]["$identifier:$socket"]))
                $next[$identifier] = $this->_X["o2i"]["$identifier:$socket"];
        }

        return $next;
    }

    /**
     * Internal method to execute signal handler on component
     *
     * @param $connections
     * @param $nextNodes
     * @param $errors
     */
    private function _handleTriggerConnections($connections, &$nextNodes, &$errors) {
        foreach($connections as $connection) {
            try {
                $nodeID = $connection["dn"];

                $nodeInfo = $this->_X["nd"][$nodeID];
                $component = $this->getModel()->getComponent( $nodeInfo["c"] );
                if($component instanceof SceneGatewayComponent && $this !== $component->getEngine()) {
                    (function($e){$this->engine=$e;})->bindTo($component, SceneGatewayComponent::class)->call($component, $this);
                }

                $sf = $this->context->getCurrentStackFrame();

                $sf->pushCycle(
                    $nodeID,
                    $nodeInfo["a"]??NULL,
                    $component->getName(),
                    NULL,
                    $connection["dk"],
                    $this->makeInputValueFetchCallback($nodeID, $sf),
                    function($socketName) use (&$nextNodes, $nodeID) {
                        $nextNodes = array_merge($nextNodes, $this->_getNextNodes([$nodeID], $socketName));
                    }
                );

                if($component instanceof ExecutableSignalTriggerNodeComponentInterface) {
                    $component->handleSignalTrigger(
                        $connection["dk"],
                        $sf->getValuesServer(),
                        $this->context
                    );
                }
            } catch (Throwable $exception) {
                $errors[] = $exception;
            } finally {
                $sf->popCycle();
            }
        }
    }


    public function triggerSignal(string $exposedSocketKey, string $componentName = NULL, $nodeIdentifier = NULL, ValueProviderInterface $valueProvider = NULL, &$errors = NULL)
    {
        if(!$this->isActive()) {
            trigger_error("Engine is not active", E_USER_ERROR);
            return NULL;
        }

        if($nodeIdentifier) {
            // if the signal was triggered directly on a node, just create an array with it
            $nodeIdentifiers = [$nodeIdentifier];
        } elseif($componentName) {
            $nodeIdentifiers = $this->_x['o'][ $componentName ][$exposedSocketKey] ?? [];
        } else {
            trigger_error("Can not find exposed socket $exposedSocketKey", E_USER_ERROR);
            $nodeIdentifiers = [];
        }

        $preparedNodes = $this->_getNextNodes( $nodeIdentifiers, $exposedSocketKey );

        try {
            $this->beginRenderCycle();

            $sf = new _PermeableStackFrame();
            $sf->valueProvider = $valueProvider;
            $this->context->pushStackFrame($sf);

            while ($prepared = $preparedNodes) {
                // Signal triggers are plain handled and not recursive.
                // After handling all node components in this cycle, continue if any of them triggers a new signal
                $preparedNodes = [];

                foreach($prepared as $nid => $connections) {
                    $this->_handleTriggerConnections($connections, $preparedNodes, $errors);
                }
            }

            return new TriggerResult($sf->cachedExposedSignals?:[], $sf->cachedExposedValues?:[]);
        } catch (Throwable $throwable) {
        } finally {
            $this->context->popStackFrame();
            $this->endRenderCycle();
        }
        return false;
    }

    /**
     * @return ComponentModelInterface
     */
    public function getModel(): ComponentModelInterface
    {
        return $this->model;
    }
}