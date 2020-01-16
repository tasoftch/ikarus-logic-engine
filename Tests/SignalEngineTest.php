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
 * SignalEngineTest.php
 * ikarus-logic-engine
 *
 * Created on 2020-01-16 15:43 by thomas
 */

use Ikarus\Logic\Data\ProjectData;
use Ikarus\Logic\Engine;
use Ikarus\Logic\Model\Component\ExecutableNodeComponent;
use Ikarus\Logic\Model\Component\Socket\ExposedInputComponent;
use Ikarus\Logic\Model\Component\Socket\ExposedOutputComponent;
use Ikarus\Logic\Model\Component\Socket\InputComponent;
use Ikarus\Logic\Model\Component\Socket\OutputComponent;
use Ikarus\Logic\Model\DataModel;
use Ikarus\Logic\Model\Executable\Context\SignalServerInterface;
use Ikarus\Logic\Model\Package\BasicTypesPackage;
use Ikarus\Logic\Model\PriorityComponentModel;
use Ikarus\Logic\ValueProvider\ValueProvider;
use PHPUnit\Framework\TestCase;

class SignalEngineTest extends TestCase
{
    protected function makeEngine($cModel, $dataModel): Engine {
        $dd = new ProjectData($dataModel);
        $engine = new Engine($cModel);
        $engine->bindData($dd);
        return $engine;
    }

    public function testSimpleInOutTrigger() {
        $engine = $this->makeEngine(
            (new PriorityComponentModel())
            ->addPackage( new BasicTypesPackage() )
            ->addComponent( new ExecutableNodeComponent("IN", [
                new ExposedOutputComponent("signal", "Signal")
            ]) )
            ->addComponent( new ExecutableNodeComponent("OUT", [
                new ExposedInputComponent("signal", "Signal")
            ]) )
            ,
            (new DataModel())
            ->addScene("sc")
            ->addNode(1, 'IN', 'sc')
            ->addNode(2, 'OUT', 'sc')
            ->connect(2, 'signal', 1, 'signal')
        );

        $engine->activate();
        $result = $engine->triggerSignal("signal", 'IN');

        $this->assertEquals([
            [2, 'signal']
        ], $result);
    }

    public function testSimpleSignalIntermediateTrigger() {
        $engine = $this->makeEngine(
            (new PriorityComponentModel())
                ->addPackage( new BasicTypesPackage() )
                ->addComponent( new ExecutableNodeComponent("IN", [
                    new ExposedOutputComponent("signal", "Signal")
                ]) )
                ->addComponent( new ExecutableNodeComponent("OUT", [
                    new ExposedInputComponent("signal", "Signal")
                ]) )
                ->addComponent( (new ExecutableNodeComponent('INTER', [
                    new InputComponent("input", 'Signal'),
                    new OutputComponent("output", "Signal")
                ]))
                    ->setSignalHandler(function($socket, SignalServerInterface $server) {
                        $server->forwardSignal('output');
                    })
                )
            ,
            (new DataModel())
                ->addScene("sc")
                ->addNode(1, 'IN', 'sc')
                ->addNode(2, 'OUT', 'sc')
                ->addNode(3, 'INTER', 'sc')
                ->connect(3, 'input', 1, 'signal')
                ->connect(2, 'signal', 3, 'output')
        );

        $engine->activate();
        $result = $engine->triggerSignal("signal", 'IN');

        $this->assertEquals([
            [2, 'signal']
        ], $result);
    }

    public function testTriggerMixedWithValues() {
        $engine = $this->makeEngine(
            (new PriorityComponentModel())
                ->addPackage( new BasicTypesPackage() )
                ->addComponent( new ExecutableNodeComponent("IN", [
                    new ExposedOutputComponent("signal", "Signal")
                ]) )
                ->addComponent( new ExecutableNodeComponent("OUT", [
                    new ExposedInputComponent("signal", "Signal")
                ]) )
                ->addComponent( (new ExecutableNodeComponent('COND', [
                    new InputComponent("input", 'Signal'),
                    new InputComponent("condition", 'Boolean'),
                    new OutputComponent("whenTrue", "Signal"),
                    new OutputComponent("whenFalse", "Signal")
                ]))
                    ->setSignalHandler(function($socket, SignalServerInterface $server) {
                        $condition = $server->fetchInputValue("condition");
                        if($condition)
                            $server->forwardSignal("whenTrue");
                        else
                            $server->forwardSignal("whenFalse");
                    })
                )
                ->addComponent(new ExecutableNodeComponent('VIN', [
                    new ExposedOutputComponent("value", 'Boolean')
                ]))
            ,
            (new DataModel())
                ->addScene("sc")
                ->addNode('in', 'IN', 'sc')
                ->addNode('goal', 'OUT', 'sc')
                ->addNode('failed', 'OUT', 'sc')
                ->addNode('vin', 'VIN', 'sc')

                ->addNode('cd', 'COND', 'sc')
                ->connect('cd', 'input', 'in', 'signal')
                ->connect('cd', 'condition', 'vin', 'value')
                ->connect('goal', 'signal', 'cd', 'whenTrue')
                ->connect('failed', 'signal', 'cd', 'whenFalse')
        );

        $engine->activate();
        $result = $engine->triggerSignal("signal", NULL, 'in');

        $this->assertEquals([
            ['failed', 'signal']
        ], $result);

        $vp = new ValueProvider();
        $vp->addValue(true, 'value', 'vin');
        $result = $engine->triggerSignal("signal", NULL, 'in', $vp);


        $this->assertEquals([
            ['goal', 'signal']
        ], $result);
    }
}
