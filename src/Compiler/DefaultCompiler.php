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

namespace Ikarus\Logic\Compiler;


use Ikarus\Logic\Compiled\Model\Project;
use Ikarus\Logic\Compiled\ProjectInterface as CompiledProjectInterface;
use Ikarus\Logic\Exception\CompilerException;
use Ikarus\Logic\Model\Component\NodeComponentExposedInputsInterface;
use Ikarus\Logic\Model\Component\NodeComponentExposedOutputsInterface;
use Ikarus\Logic\Model\Component\Socket\Type\SignalTypeInterface;
use Ikarus\Logic\Model\Element\Connection\ConnectionElementInterface;
use Ikarus\Logic\Model\Element\Socket\InputSocketElement;
use Ikarus\Logic\Model\Element\Socket\SocketElementInterface;
use Ikarus\Logic\Model\ProjectInterface as ModelProjectInterface;
use TASoft\Util\ValueInjector;

class DefaultCompiler implements CompilerInterface
{
    public function compileProject(ModelProjectInterface $project): CompiledProjectInterface
    {
        $proj = new Project();
        $vi = new ValueInjector($proj, Project::class);

        $components = [];
        $types = [];

        $exposedInputs = [];
        $exposedOutputs = [];
        $signalTriggers = [];

        $connections = ["i2o" => [], 'o2i' => []];


        set_error_handler(function($code, $msg, $file, $line) {
            return $this->handleCompilerError($code, $msg, $file, $line);
        });

        try {
            foreach($project->getScenes() as $scene) {
                if($project->isTopLevelScene($scene)) {
                    foreach($scene->getNodes() as $node) {
                        $nc = $node->getComponent();
                        $ncn = $nc->getName();

                        $components[$ncn] = $nc;

                        $socketIterator = function($sockets, $name) {
                            /** @var SocketElementInterface $socket */
                            foreach($sockets as $socket) {
                                if($socket->getComponent()->getName() == $name)
                                    return $socket;
                            }
                            return NULL;
                        };

                        $nid = $node->getIdentifier();

                        if($nc instanceof NodeComponentExposedInputsInterface) {
                            foreach($nc->getExposedInputNames() as $name) {
                                $input = $socketIterator( $node->getInputSocketElements(), $name );
                                if(!$input)
                                    trigger_error("Input $name not found of node $ncn", E_USER_ERROR);

                                $exposedInputs["$nid:$name"] = $input;
                                $types[ $input->getType()->getName() ] = $input->getType();
                            }
                        }

                        if($nc instanceof NodeComponentExposedOutputsInterface) {
                            foreach($nc->getExposedOutputNames() as $name) {
                                $output = $socketIterator( $node->getOutputSocketElements(), $name );
                                if(!$output)
                                    trigger_error("Output $name not found of node $ncn", E_USER_ERROR);

                                if($output->getType() instanceof SignalTypeInterface) {
                                    $signalTriggers[ "$nid:$name" ] = $output;
                                } else {
                                    $exposedOutputs["$nid:$name"] = $output;
                                }
                                $types[ $output->getType()->getName() ] = $output->getType();
                            }
                        }
                    }

                    foreach($scene->getConnections() as $connection) {
                        if($connection->getOutputSocketElement()->getType() instanceof SignalTypeInterface) {
                            // Signals always go from output to input
                            $k = sprintf("%s:%s", $connection->getOutputSocketElement()->getNode()->getIdentifier(), $connection->getOutputSocketElement()->getComponent()->getName());
                            $connections["o2i"][$k] = $connection;
                        } else {
                            // Exporessions go from input to output
                            $k = sprintf("%s:%s", $connection->getInputSocketElement()->getNode()->getIdentifier(), $connection->getInputSocketElement()->getComponent()->getName());
                            $connections["i2o"][$k] = $connection;
                        }
                    }
                }
            }


            $i2o = [];
            /** @var ConnectionElementInterface $connection */
            foreach($connections["i2o"] as $connection) {
                $k = sprintf("%s:%s", $connection->getInputSocketElement()->getNode()->getIdentifier(), $connection->getInputSocketElement()->getComponent()->getName());
                $i2o[$k]["src"] = $connection->getInputSocketElement()->getNode()->getComponent();
                $i2o[$k]["dst"] = $connection->getOutputSocketElement()->getNode()->getComponent();
                $i2o[$k]["dst#"] = $connection->getOutputSocketElement()->getNode()->getIdentifier();

                $i2o[$k]["key"] = $connection->getOutputSocketElement()->getComponent()->getName();
            }

            $vi->inputs = array_keys($exposedInputs);
            $vi->componentNames = array_keys($components);
            $vi->typeNames = array_keys($types);
            $vi->i2o = $i2o;

        } catch (\Throwable $throwable) {
            throw $throwable;
        } finally {
            restore_error_handler();
        }

        return $proj;
    }

    protected function createInputAST(InputSocketElement $input, $connections) {
        $data = ['c' => $input->getNode()->getComponent()];
        $k = sprintf("%s:%s", $input->getNode()->getIdentifier(), $input->getComponent()->getName());
        /** @var ConnectionElementInterface $conn */
        $conn = $connections["i2o"][ $k ] ?? NULL;
        if($conn) {
            $data["t"] = $conn->getOutputSocketElement()->getNode()->getComponent();
        }
        return $data;
    }

    protected function handleCompilerError($code, $message, $file, $line) {
        throw new CompilerException($message, $code);
    }
}