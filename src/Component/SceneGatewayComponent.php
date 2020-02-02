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

namespace Ikarus\Logic\Component;


use Ikarus\Logic\Component\Socket\InputGatewaySocketComponent;
use Ikarus\Logic\Component\Socket\OutputGatewaySocketComponent;
use Ikarus\Logic\Engine;
use Ikarus\Logic\EngineInterface;
use Ikarus\Logic\Internal\_RuntimeContext;
use Ikarus\Logic\Internal\StackFrame\_StackFrame;
use Ikarus\Logic\Model\Component\AbstractNodeComponent;
use Ikarus\Logic\Model\Component\Socket\InputSocketComponentInterface;
use Ikarus\Logic\Model\Component\Socket\OutputSocketComponentInterface;
use Ikarus\Logic\Model\Component\Socket\SocketComponentInterface;
use Ikarus\Logic\Model\Data\Node\NodeDataModelInterface;
use Ikarus\Logic\Model\Executable\Context\RuntimeContextInterface;
use Ikarus\Logic\Model\Executable\Context\SignalServerInterface;
use Ikarus\Logic\Model\Executable\Context\ValuesServerInterface;
use Ikarus\Logic\Model\Executable\ExecutableExpressionNodeComponentInterface;
use Ikarus\Logic\Model\Executable\ExecutableSignalTriggerNodeComponentInterface;
use Ikarus\Logic\ValueProvider\CallbackValueProvider;
use Throwable;

/**
 * Use this component to jump from one scene into another inside of a project.
 * Please note, that the Ikarus Logic only support this component class for that action!
 * You are allowed to create subclasses to adjust for editor, localization, ...  but you must not change the name!
 *
 * It is similar to a function call:
 *  Jumping into another scene internal pushes a stack frame. So any input and output values cache is now empty for that frame.
 *  Example:
 *      Scene A: Has Node IN_1 and IN_2 which expose an output each.
 *               It also has a Node OUT_1 which expose an input.
 *
 *      The gateway component makes those three sockets accessible.
 *
 *      Scene B: Has a node LINK_1 (component "GATEWAY") and a connection to output (OUT_1, which is paired with exposed input of node OUT_1 of scene A)
 *
 * @package Ikarus\Logic\Component
 * @method _updateNode($n, $s)  // Suppress warnings in this document.
 */
final class SceneGatewayComponent extends AbstractNodeComponent implements ExecutableSignalTriggerNodeComponentInterface, ExecutableExpressionNodeComponentInterface, EngineDependentComponentInterface
{
    // The engine gets injected into this component
    /** @var EngineInterface */
    private $engine;

    public function getName(): string
    {
        return "IKARUS.GATEWAY";
    }

    /**
     * This is an internal method called by the connection compiler to resolve scene references.
     *
     * @param $name
     * @param NodeDataModelInterface $node
     * @param $gateways
     * @return InputGatewaySocketComponent|OutputGatewaySocketComponent|null
     * @internal
     */
    final public function getDynamicSocket($name, $node, $gateways) {
        $nid = $node->getIdentifier();
        if($gateway = $gateways[$nid][$name] ?? NULL) {
            /**
             * @var SocketComponentInterface $socket
             * @var NodeDataModelInterface $nodeComponent
             */
            list($socket, $nodeComponent) = $gateway;

            if($socket instanceof InputSocketComponentInterface) {
                return new InputGatewaySocketComponent($name, $socket, $nodeComponent);
            } elseif($socket instanceof OutputSocketComponentInterface) {
                return new OutputGatewaySocketComponent($name, $socket, $nodeComponent);
            }
        }
        return NULL;
    }

    public function updateNode(ValuesServerInterface $valuesServer, RuntimeContextInterface $context)
    {
        $attributes = $context->getNodeAttributes()["gw"] ?? NULL;

        if($attr = $attributes[ $context->getRequestedOutputSocketName() ]) {
            $dstNode = $attr["dn"];
            $dstKey = $attr["dk"];

            $sf = new _StackFrame();
            /** @var _RuntimeContext $context */
            $currentFrame = $context->getCurrentStackFrame();

            $sf->valueProvider = new CallbackValueProvider(function($socketName, $nodeIdentifier) use ($attributes, $valuesServer, $currentFrame) {
                if($key = array_search(['dn' => $nodeIdentifier, 'dk' => $socketName], $attributes)) {
                    return $valuesServer->fetchInputValue($key);
                }
                return $currentFrame->getValueProvider() ? $currentFrame->getValueProvider()->getValue($socketName, $nodeIdentifier) : NULL;
            });


            $context->pushStackFrame($sf);
            $this->engine->beginRenderCycle();

            try {
                $output = (function($ni, $sck){return$this->_updateNode($ni, $sck);})->bindTo($this->engine, Engine::class)->call($this->engine, $dstNode, $dstKey);
            } catch (Throwable $exception) {
                throw $exception;
            } finally {
                $this->engine->endRenderCycle();
                $context->popStackFrame();
            }
            $nodeIdentifier = $context->getNodeIdentifier();


            foreach($output as $socket => $value) {
                if($key = array_search(['dn' => $dstNode, 'dk' => $socket], $attributes)) {
                    $currentFrame->cachedOutputValues["$nodeIdentifier:$key"] = $value;
                }
            }
        }
    }

    public function handleSignalTrigger(string $onInputSocketName, SignalServerInterface $signalServer, RuntimeContextInterface $context)
    {
        $attributes = $context->getNodeAttributes()["gw"] ?? NULL;
        if($attr = $attributes[$onInputSocketName]) {
            $dstNode = $attr["dn"];
            $dstKey = $attr["dk"];

            $sf = new _StackFrame();
            /** @var _RuntimeContext $context */
            $currentFrame = $context->getCurrentStackFrame();
            $sf->valueProvider = new CallbackValueProvider(function($socketName, $nodeIdentifier) use ($attributes, $signalServer, $currentFrame) {
                if($key = array_search(['dn' => $nodeIdentifier, 'dk' => $socketName], $attributes)) {
                    return $signalServer->fetchInputValue($key);
                }
                return $currentFrame->getValueProvider() ? $currentFrame->getValueProvider()->getValue($socketName, $nodeIdentifier) : NULL;
            });

            $context->pushStackFrame($sf);
            $this->engine->beginRenderCycle();

            try {
                $output = $this->engine->triggerSignal($dstKey, NULL, $dstNode, NULL);
            } catch (Throwable $exception) {
                throw $exception;
            } finally {
                $this->engine->endRenderCycle();
                $context->popStackFrame();
            }

            foreach($output->getExposedSignals() as $signal) {
                list($nid, $socket) = $signal;
                if($key = array_search(['dn' => $nid, 'dk' => $socket], $attributes)) {
                    $signalServer->forwardSignal($key);
                }
            }

            $nodeIdentifier = $context->getNodeIdentifier();
            foreach($output->getExposedValues() as $socket => $value) {
                if($key = array_search(['dn' => $dstNode, 'dk' => $socket], $attributes)) {
                    $currentFrame->cachedOutputValues["$nodeIdentifier:$key"] = $value;
                }
            }
        }
    }

    /**
     * @return Engine|null
     */
    public function getEngine(): EngineInterface
    {
        return $this->engine;
    }

    public function setEngine(EngineInterface $engine)
    {
        $this->engine = $engine;
    }
}