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

use Ikarus\Logic\Data\ProjectData;
use Ikarus\Logic\Engine;
use Ikarus\Logic\Model\Component\ExecutableNodeComponent;
use Ikarus\Logic\Model\Component\Socket\ExposedInputComponent;
use Ikarus\Logic\Model\DataModel;
use Ikarus\Logic\Model\Package\BasicTypesPackage;
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
            ->addComponent(new ExecutableNodeComponent("OUT2", [
                new ExposedInputComponent('input1', 'Any'),
                new ExposedInputComponent('input2', 'Any')
            ]))
                ->addComponent(new ExecutableNodeComponent("IN", [
                    new ExposedInputComponent('output', 'Number')
                ]))
            ,
            (new DataModel())
            ->addScene("A")
            ->addNode('out', 'OUT2', 'A')
        );

        $engine->activate();

        $vp = new ValueProvider();
        $vp->addValue(23, 'output', 'in1');
        $vp->addValue(44, 'output', 'in2');


        print_r($engine->updateNode("out", $vp));
    }
}
