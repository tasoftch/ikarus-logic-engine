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
 * MultipleOutputsTest.php
 * ikarus-logic-engine
 *
 * Created on 2020-01-03 11:46 by thomas
 */

use Ikarus\Logic\Compiler\CompilerResult;
use Ikarus\Logic\Compiler\Consistency\FullConsistencyCompiler;
use Ikarus\Logic\Compiler\Executable\FullExecutableCompiler;
use Ikarus\Logic\Data\CompilerResultData;
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

class MultipleOutputsTest extends TestCase
{
    private function createModel(&$count) {
        $cModel = new PriorityComponentModel();
        $cModel->addPackage( new BasicTypesPackage() );

        $cModel->addComponent(
            (new ExecutableNodeComponent("math", [
                new InputComponent("leftOperand", "Number"),
                new InputComponent("rightOperand", "Number"),
                new OutputComponent("result", "Number")
            ]))
                ->setUpdateHandler(function(ValuesServerInterface $valuesServer, RuntimeContextInterface $context) use (&$count) {
                    $op = $context->getNodeAttributes()["operation"];
                    $leftOperand = $valuesServer->fetchInputValue("leftOperand");
                    $rightOperand = $valuesServer->fetchInputValue("rightOperand");

                    $result = NULL;
                    $count++;

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

        $cModel->addComponent( new ExecutableNodeComponent("doubleOutputs", [
            new ExposedInputComponent("value1", "Number"),
            new ExposedInputComponent("value2", "Number"),
        ]) );

        return $cModel;
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

    public function testMultipleOutputs() {
        $engine = $this->makeEngine(
            $this->createModel($count)
            ,
            (new DataModel())
                ->addScene("myScene")
                ->addNode("ask1", 'userInput', "myScene")
                ->addNode("ask2", 'userInput', 'myScene')
                ->addNode("mate", "math", 'myScene', ['operation' => '-'])
                ->addNode("out", "doubleOutputs", 'myScene')
                ->addNode("mate2", "math", 'myScene', ['operation' => '+'])

                ->connect('mate', 'leftOperand', 'ask1', 'enteredNumber')
                ->connect('mate', 'rightOperand', 'ask2', 'enteredNumber')
                ->connect('out', 'value1', 'mate', 'result')
                ->connect('out', 'value2', 'mate2', 'result')
                ->connect('mate2', 'leftOperand', 'ask1', 'enteredNumber')
                ->connect('mate2', 'rightOperand', 'ask2', 'enteredNumber')
        );

        $count = 0;
        $vp = new ValueProvider();
        $vp->addValue(35, 'enteredNumber', 'ask1');
        $vp->addValue(20, 'enteredNumber', 'ask2');

        $engine->activate();

        $value1 = $engine->requestValue('out', 'value1', $vp);
        $this->assertEquals(1, $count);

        $value2 = $engine->requestValue('out', 'value2', $vp);


        $this->assertEquals(15, $value1);
        $this->assertEquals(55, $value2);
        $this->assertEquals(2, $count);
    }
}
