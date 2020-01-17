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

namespace Ikarus\Logic\Component;


use Ikarus\Logic\Model\Component\AbstractNodeComponent;

/**
 * Use this component to jump from one scene into another inside of a project.
 * Please note, that the Ikarus Logic only support this component class for that action!
 * You are allowed to create subclasses to adjust for editor, localization, ...  but you must not change the name!
 *
 * It is similar to a function call:
 *  Jumping into another scene internal pushes a stack frame. So any input and output values cache is now empty for that frame.
 *  Example:
 *      Scene A: Has Node IN_1 and IN_2 which expose an output each.
 *               It also has a Node OUT_1 which expose an input.
 *
 *      The gateway component makes those three sockets accessible.
 *
 *      Scene B: Has a node LINK_1 (component "GATEWAY") and a connection to output (OUT_1, which is paired with exposed input of node OUT_1 of scene A)
 *
 * @package Ikarus\Logic\Component
 */
class SceneGatewayComponent extends AbstractNodeComponent
{
    final public function getName(): string
    {
        return "IKARUS.GATEWAY";
    }


}