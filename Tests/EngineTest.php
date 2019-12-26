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
 * EngineTest.php
 * ikarus-logic-engine
 *
 * Created on 2019-12-25 11:05 by thomas
 */

use Ikarus\Logic\Compiler\CompilerResult;
use Ikarus\Logic\Compiler\Consistency\FullConsistencyCompiler;
use Ikarus\Logic\Compiler\Executable\FullExecutableCompiler;
use Ikarus\Logic\Data\CompilerResultData;
use Ikarus\Logic\Data\PHPFileData;
use Ikarus\Logic\Engine;
use Ikarus\Logic\Model\Component\ComponentModelInterface;
use Ikarus\Logic\Model\Component\ExecutableNodeComponent;
use Ikarus\Logic\Model\Component\Socket\ExposedInputComponent;
use Ikarus\Logic\Model\Component\Socket\ExposedOutputComponent;
use Ikarus\Logic\Model\Component\Socket\InputComponent;
use Ikarus\Logic\Model\Component\Socket\OutputComponent;
use Ikarus\Logic\Model\Data\DataModelInterface;
use Ikarus\Logic\Model\DataModel;
use Ikarus\Logic\Model\Executable\Context\RuntimeContextInterface;
use Ikarus\Logic\Model\Executable\Context\ValuesServerInterface;
use Ikarus\Logic\Model\Package\BasicTypesPackage;
use Ikarus\Logic\Model\PriorityComponentModel;
use Ikarus\Logic\ValueProvider\ValueProvider;
use PHPUnit\Framework\TestCase;

class EngineTest extends TestCase
{
    private function createModel() {
        $cModel = new PriorityComponentModel();
        $cModel->addPackage( new BasicTypesPackage() );

        $cModel->addComponent(
            (new ExecutableNodeComponent("math", [
                new InputComponent("leftOperand", "Number"),
                new InputComponent("rightOperand", "Number"),
                new OutputComponent("result", "Number")
            ]))
            ->setUpdateHandler(function(ValuesServerInterface $valuesServer, RuntimeContextInterface $context) {
                $op = $context->getNodeAttributes()["operation"];
                $leftOperand = $valuesServer->fetchInputValue("leftOperand");
                $rightOperand = $valuesServer->fetchInputValue("rightOperand");

                $result = NULL;

                switch ($op) {
                    case '+': $result = $leftOperand + $rightOperand; break;
                    case '-': $result = $leftOperand - $rightOperand; break;
                    case '*': $result = $leftOperand * $rightOperand; break;
                    case '/': $result = $leftOperand / $rightOperand; break;
                    default:
                }

                $valuesServer->pushOutputValue('result', $result);
            })
        );

        $cModel->addComponent( new ExecutableNodeComponent("userInput", [
            new ExposedOutputComponent("enteredNumber", "Number")
            // If the component handler does not provide a value, the engine will follow the exposure and ask the parent scope for that value.
        ]) );

        $cModel->addComponent(
            (new ExecutableNodeComponent("displayDialog", [
                new InputComponent("message", "String"),
                new OutputComponent("clickedButton", "Number")
            ]))
            ->setUpdateHandler(function(ValuesServerInterface $valuesServer) {
                $message = $valuesServer->fetchInputValue('message');
                switch ($message) {
                    case 10:
                    case 20:
                    case 30:
                        $valuesServer->pushOutputValue('clickedButton', 13); break;
                    case 5:
                    case 6:
                    case 7:
                        $valuesServer->pushOutputValue('clickedButton', 6); break;
                    default:
                        $valuesServer->pushOutputValue('clickedButton', -1);
                }
            })
        );

        $cModel->addComponent( new ExecutableNodeComponent("askForPermission", [
            new ExposedInputComponent("clickedButton", "Number")
        ]) );

        return $cModel;
    }

    public function testEngine() {
        $engine = new Engine( $this->createModel() );

        $ds = new PHPFileData("Tests/test.storage.php");
        $engine->bindData($ds);

        $engine->activate();
        $this->assertTrue( $engine->isActive() );

        $result = $engine->requestValue("outputAnswer", "clickedButton");
        $this->assertSame(-1, $result);

        $vp = new ValueProvider();
        $vp->addValue(6, 'enteredNumber', 'askUser1');
        $vp->addValue(14, 'enteredNumber', 'askUser2');

        $result = $engine->requestValue("outputAnswer", "clickedButton", $vp);
        $this->assertSame(13, $result);

        $vp->addValue(3, 'enteredNumber', 'askUser1');
        $vp->addValue(4, 'enteredNumber', 'askUser2');

        $result = $engine->requestValue("outputAnswer", "clickedButton", $vp);
        $this->assertSame(6, $result);
    }

    private function makeEngine(ComponentModelInterface $cModel, DataModelInterface $dataModel): Engine {
        $compiler = new FullConsistencyCompiler($cModel);
        $result = new CompilerResult();
        $compiler->compile($dataModel, $result);

        $compiler = new FullExecutableCompiler($cModel);
        $compiler->compile($dataModel, $result);

        $data = new CompilerResultData($result);
        $engine = new Engine($cModel);
        $engine->bindData($data);
        return $engine;
    }

    public function testCycleStacking() {
        $updateCount = 0;

        $engine = $this->makeEngine(
            (new PriorityComponentModel())
                ->addPackage(new BasicTypesPackage())
                ->addComponent(new ExecutableNodeComponent("OUT", [
                    new ExposedInputComponent("input", "String")
                ]))
                ->addComponent((new ExecutableNodeComponent("HAND", [
                    new ExposedInputComponent("value", "Number"),
                    new ExposedInputComponent("format", "String"),
                    new OutputComponent("string", "String")
                ]))
                    ->setUpdateHandler(function(ValuesServerInterface $server) use (&$updateCount) {
                        $updateCount++;
                        $value = $server->fetchInputValue("value");
                        $format = $server->fetchInputValue("format");

                        $server->pushOutputValue('string', sprintf($format, $value));
                    })
                ),
            (new DataModel())
                ->addScene("myScene")
                ->addNode('node1', "OUT", "myScene")
                ->addNode("node2", "HAND", 'myScene')
                ->addNode("node3", "OUT", "myScene")
                ->connect("node3", "input", "node2", "string")
                ->connect("node1", "input", "node2", "string")
        );

        $engine->activate();
        $result = $engine->requestValue("node1", "input");
        $this->assertSame("", $result);

        // Only 1 times updated, because only one node did request
        $this->assertEquals(1, $updateCount);

        $vp = new ValueProvider();
        $vp->addValue(200, "value", 'node2');
        $vp->addValue('%dms', "format", 'node2');

        $result = $engine->requestValue("node3", "input", $vp);
        $this->assertSame("200ms", $result);

        $this->assertEquals(2, $updateCount);

        $updateCount = 0;

        $vp->addValue(200, "value", 'node2');
        $vp->addValue('%dms', "format", 'node2');


        $result1 = $engine->requestValue("node3", "input", $vp);

        $vp->addValue(300, "value", 'node2');
        $vp->addValue('%dms', "format", 'node2');

        $result2 = $engine->requestValue("node1", "input", $vp);



        $this->assertSame("200ms", $result1);
        $this->assertSame('300ms', $result2);

        $this->assertSame(2, $updateCount);
    }

    public function testInternalCachingBetweenCycles() {
        $updateCount = 0;

        $engine = $this->makeEngine(
            (new PriorityComponentModel())
                ->addPackage(new BasicTypesPackage())
                ->addComponent(new ExecutableNodeComponent("OUT", [
                    new ExposedInputComponent("input", "String")
                ]))
                ->addComponent((new ExecutableNodeComponent("HAND", [
                    new ExposedInputComponent("value", "Number"),
                    new ExposedInputComponent("format", "String"),
                    new OutputComponent("string", "String")
                ]))
                    ->setUpdateHandler(function(ValuesServerInterface $server) {
                        $value = $server->fetchInputValue("value");
                        $format = $server->fetchInputValue("format");

                        $server->pushOutputValue('string', sprintf($format, $value));
                    })
                )
                ->addComponent((new ExecutableNodeComponent("math", [
                    new ExposedInputComponent("left", "Number"),
                    new ExposedInputComponent("right", "Number"),
                    new OutputComponent("result", "Number")
                ]))
                    ->setUpdateHandler(function(ValuesServerInterface $server) use (&$updateCount) {
                        $updateCount++;
                        $left = $server->fetchInputValue("left");
                        $right = $server->fetchInputValue("right");

                        $server->pushOutputValue('result', $left + $right);
                    })
                )
                ->addComponent(new ExecutableNodeComponent("IN", [
                    new ExposedOutputComponent("userInput", "Number")
                ])),
            (new DataModel())
                ->addScene("myScene")
                ->addNode('out', "OUT", "myScene")
                ->addNode("handler", "HAND", 'myScene')
                ->addNode("myMath", "math", "myScene")
                ->addNode("myMath2", "math", "myScene")
                ->addNode("myIn", "IN", "myScene")

                ->connect("out", "input", "handler", "string")
                ->connect("handler", "value", "myMath", "result")
                ->connect("myMath", "right", "myMath2", "result")
                ->connect("myMath", "left", "myIn", "userInput")
                ->connect("myMath2", "left", "myIn", "userInput")
        );

        $engine->activate();

        $vp = new ValueProvider();
        $vp->addValue(10, "userInput", "myIn");
        $vp->addValue(5, "right", 'myMath2');
        $vp->addValue('%dms', "format", 'handler');

        $result = $engine->requestValue("out", "input", $vp);
        $this->assertSame("25ms", $result);
    }
}
