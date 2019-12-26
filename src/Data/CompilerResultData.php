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

namespace Ikarus\Logic\Data;


use Ikarus\Logic\Compiler\CompilerResult;
use Ikarus\Logic\Compiler\Executable\ExecutableCompiler;
use Ikarus\Logic\Compiler\Executable\ExposedSocketsCompiler;
use Ikarus\Logic\Model\Component\ComponentModelInterface;
use Ikarus\Logic\Model\Exception\LogicException;

class CompilerResultData implements DataInterface
{
    /** @var CompilerResult */
    private $compilerResult;

    /**
     * CompilerResultData constructor.
     * @param CompilerResult $compilerResult
     */
    public function __construct(CompilerResult $compilerResult)
    {
        $this->compilerResult = $compilerResult;
    }


    public function getData(ComponentModelInterface $componentModel)
    {
        $exec = $this->getCompilerResult()->getAttribute( ExecutableCompiler::RESULT_ATTRIBUTE_EXECUTABLE );
        if(!$exec) {
            throw new LogicException("No executable found", LogicException::CODE_SYMBOL_NOT_FOUND);
        }
        $exposed = $this->getCompilerResult()->getAttribute( ExposedSocketsCompiler::RESULT_ATTRIBUTE_EXPOSED_SOCKETS );
        return ['x' => $exposed, 'X' => $exec];
    }

    /**
     * @return CompilerResult
     */
    public function getCompilerResult(): CompilerResult
    {
        return $this->compilerResult;
    }
}