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

/**
 * GatewayTest.php
 * ikarus-logic-engine
 *
 * Created on 2020-01-17 17:18 by thomas
 */

use Ikarus\Logic\Component\SceneGatewayComponent;
use Ikarus\Logic\Data\ProjectData;
use Ikarus\Logic\Engine;
use Ikarus\Logic\Model\Component\ExecutableNodeComponent;
use Ikarus\Logic\Model\Component\Socket\ExposedInputComponent;
use Ikarus\Logic\Model\Component\Socket\ExposedOutputComponent;
use Ikarus\Logic\Model\Data\Scene\AttributedSceneDataModelInterface;
use Ikarus\Logic\Model\DataModel;
use Ikarus\Logic\Model\Executable\Context\RuntimeContextInterface;
use Ikarus\Logic\Model\Executable\Context\SignalServerInterface;
use Ikarus\Logic\Model\Package\BasicTypesPackage;
use Ikarus\Logic\Model\Package\ExposedSocketsPackage;
use Ikarus\Logic\Model\PriorityComponentModel;
use Ikarus\Logic\ValueProvider\ValueProvider;
use PHPUnit\Framework\TestCase;

class GatewayTest extends TestCase
{
    protected function makeEngine($cModel, $dataModel): Engine {
        $dd = new ProjectData($dataModel);
        $engine = new Engine($cModel);
        $engine->bindData($dd);
        return $engine;
    }

    public function testSimpleGateway() {
        $engine = $this->makeEngine(
            (new PriorityComponentModel())
                ->addPackage(new BasicTypesPackage())
                ->addComponent(new SceneGatewayComponent())
                ->addPackage(new ExposedSocketsPackage('Any'))
                ->addComponent(new ExecutableNodeComponent('OUT', [
                    new ExposedInputComponent("input1", 'Any'),
                    new ExposedInputComponent("input2", 'Any')
                ]))
            ,
            (new DataModel())
                ->addScene("myScene")
                ->addNode("node", 'IKARUS.GATEWAY', 'myScene')
                ->addNode("node2", 'IKARUS.GATEWAY', 'myScene')

                ->addNode("out", "OUT", 'myScene')
                ->addNode('in1', 'IKARUS.IN.ANY', 'myScene')
                ->addNode('in2', 'IKARUS.IN.ANY', 'myScene')

                ->addScene("linked", [ AttributedSceneDataModelInterface::ATTR_HIDDEN => 1 ])
                ->addNode("exp_input", 'IKARUS.IN.ANY', 'linked')
                ->addNode("exp_output", 'IKARUS.OUT.ANY', 'linked')

                ->connect('node', 'myInput', 'in1', 'output')
                ->connect('node2', 'myInput', 'in2', 'output')

                ->connect('out', 'input1', 'node', 'myOutput')
                ->connect('out', 'input2', 'node2', 'myOutput')

                ->connect("exp_output", 'input', 'exp_input', 'output')

                ->pair('linked', 'node', [
                    'myInput' => 'exp_input.output',
                    'myOutput' => 'exp_output.input'
                ])
                ->pair('linked', 'node2', [
                    'myInput' => 'exp_input.output',
                    'myOutput' => 'exp_output.input'
                ])
        );

        $engine->activate();

        $vp = new ValueProvider();

        $vp->addValue(23, 'output', 'in1');
        $vp->addValue(44, 'output', 'in2');


        $this->assertEquals([
            'input1' => 23,
            'input2' => 44
        ], $engine->updateNode("out", $vp));
    }

    public function testSignalGateway() {
        $engine = $this->makeEngine(
            (new PriorityComponentModel())
                ->addPackage(new BasicTypesPackage())
                ->addComponent(new SceneGatewayComponent())
                ->addPackage(new ExposedSocketsPackage('Signal', 'Any'))
                ->addComponent(new ExecutableNodeComponent('EVENT', [
                    new ExposedOutputComponent("buttonPressed", 'Signal'),
                    new ExposedOutputComponent("buttonReleased", 'Signal')
                ])
                )
                ->addComponent((new ExecutableNodeComponent('SIG_OUT', [
                    new ExposedInputComponent("signal", 'Signal'),
                    new ExposedInputComponent("value", 'Any')
                ]))
                    ->setSignalHandler(function($socket, SignalServerInterface $server) {
                        $value = $server->fetchInputValue('value');
                        $server->exposeValue('value', $value);
                        $server->exposeSignal($socket);
                    })
                )
            ,
            (new DataModel())
                ->addScene("myScene")
                ->addNode("start", 'EVENT', 'myScene')
                ->addNode("linker", 'IKARUS.GATEWAY', 'myScene')
                ->addNode('sigOut', 'SIG_OUT', 'myScene')
                ->addNode('in', 'IKARUS.IN.ANY', 'myScene')

                ->connect('linker', 'testIn1', 'start', 'buttonPressed')
                ->connect('linker', 'testIn2', 'start', 'buttonReleased')
                ->connect('sigOut', 'signal', 'linker', 'heheSignal')
                ->connect('sigOut', 'value', 'in', 'output')


                ->addScene("linked", [ AttributedSceneDataModelInterface::ATTR_HIDDEN => 1 ])
                ->addNode('entry', 'IKARUS.IN.SIGNAL', 'linked')
                ->addNode('exit', 'IKARUS.OUT.SIGNAL', 'linked')

                ->connect('exit', 'input', 'entry', 'output')

                ->pair('linked', 'linker', [
                    'testIn1' => 'entry.output',
                    'testIn2' => 'entry.output',
                    'heheSignal' => 'exit.input'
                ])
        );

        $engine->activate();

        $result = $engine->triggerSignal('buttonPressed', NULL, 'start');
        print_r($result);
    }
}
