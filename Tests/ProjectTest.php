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
 * ProjectTest.php
 * ikarus-logic-engine
 *
 * Created on 2019-12-18 14:12 by thomas
 */

use Ikarus\Logic\Compiler\DefaultCompiler;
use Ikarus\Logic\Model\Component\AbstractNodeComponent;
use Ikarus\Logic\Model\Component\NodeComponentExposedInputsInterface;
use Ikarus\Logic\Model\Component\Socket\InputComponent;
use Ikarus\Logic\Model\Component\Socket\OutputComponent;
use Ikarus\Logic\Model\Loader\DataLoader;
use Ikarus\Logic\Model\LogicProject;
use Ikarus\Logic\Model\Package\BasicTypesPackage;
use PHPUnit\Framework\TestCase;

class ProjectTest extends TestCase
{
    public function testProject() {
        $project = new LogicProject();

        $project->addPackage( new BasicTypesPackage() );

        $project->addComponent( new TestNodeComponent() );
        $project->addComponent( new MainInputNodeComponent() );

        $dl = new DataLoader([
            'scenes' => [
                [
                    'id' => 1,
                    'name' => 'default',
                    'nodes' => [
                        [
                            'id' => 2,
                            'name' => 'test',
                        ],
                        [
                            'id' => 3,
                            'name' => 'test'
                        ],
                        [
                            "id" => 4,
                            "name" => 'main-inp'
                        ]
                    ],
                    'connections' => [
                        [
                            'src' => 2,
                            'output' => 'output',
                            'dst' => 3,
                            'input' => 'input'
                        ],
                        [
                            'src' => 2,
                            'output' => 'output',
                            'dst' => 4,
                            'input' => 'input'
                        ]
                    ]
                ]
            ]
        ], $project);

        $dl->getProject();

        $compiler = new DefaultCompiler();
        $cpl = $compiler->compileProject( $project );
        print_r($cpl);
    }
}

class TestNodeComponent extends AbstractNodeComponent {
    public function getName(): string
    {
        return "test";
    }

    public function getInputSockets(): ?array
    {
        return [
            new InputComponent("input", "Any")
        ];
    }

    public function getOutputSockets(): ?array
    {
        return [
            new OutputComponent("output", "String")
        ];
    }
}

class MainInputNodeComponent extends AbstractNodeComponent implements NodeComponentExposedInputsInterface {
    public function getName(): string
    {
        return "main-inp";
    }

    public function getInputSockets(): ?array
    {
        return [
            new InputComponent("input", "Any")
        ];
    }

    public function getExposedInputNames(): array
    {
        return ["input"];
    }


}