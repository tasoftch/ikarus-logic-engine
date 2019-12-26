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

use Ikarus\Logic\Data\PHPFileData;
use Ikarus\Logic\Engine;
use Ikarus\Logic\Model\Component\ExecutableNodeComponent;
use Ikarus\Logic\Model\Component\Socket\ExposedInputComponent;
use Ikarus\Logic\Model\Component\Socket\ExposedOutputComponent;
use Ikarus\Logic\Model\Component\Socket\InputComponent;
use Ikarus\Logic\Model\Component\Socket\OutputComponent;
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
}
