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
 * FileStorageTest.php
 * ikarus-logic-engine
 *
 * Created on 2019-12-23 19:46 by thomas
 */

use Ikarus\Logic\Data\PHPFileData;
use Ikarus\Logic\Model\Component\NodeComponent;
use Ikarus\Logic\Model\Component\Socket\ExposedInputComponent;
use Ikarus\Logic\Model\Component\Socket\ExposedOutputComponent;
use Ikarus\Logic\Model\Component\Socket\InputComponent;
use Ikarus\Logic\Model\Component\Socket\OutputComponent;
use Ikarus\Logic\Model\Package\BasicTypesPackage;
use Ikarus\Logic\Model\PriorityComponentModel;
use PHPUnit\Framework\TestCase;

class FileStorageTest extends TestCase
{
    public function testFileStorage() {
        $cModel = new PriorityComponentModel();
        $cModel->addPackage( new BasicTypesPackage() );

        $cModel->addComponent( new NodeComponent("math", [
            new InputComponent("leftOperand", "Number"),
            new InputComponent("rightOperand", "Number"),
            new OutputComponent("result", "Number")
        ]) );

        $cModel->addComponent( new NodeComponent("userInput", [
            // If you have a node obtaining values, it will provide them via outputs to other nodes inputs.
            new ExposedOutputComponent("enteredNumber", "Number")
        ]) );

        $cModel->addComponent( new NodeComponent("displayDialog", [
            new InputComponent("message", "String"),
            new OutputComponent("clickedButton", "Number")
        ]) );

        $cModel->addComponent( new NodeComponent("askForPermission", [
            new ExposedInputComponent("clickedButton", "Number")
        ]) );

        $storage = new PHPFileData("Tests/test.storage.php");
        print_r($storage->getData($cModel));
    }
}
