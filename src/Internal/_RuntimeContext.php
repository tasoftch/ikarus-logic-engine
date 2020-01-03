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

namespace Ikarus\Logic\Internal;


use Ikarus\Logic\Internal\StackFrame\_StackFrame;
use Ikarus\Logic\Model\Executable\Context\RuntimeContextInterface;

class _RuntimeContext implements RuntimeContextInterface
{
    /** @var _StackFrame[] */
    private $stackFrames = [];

    private $foreverComponents = [];
    private $foreverNodes = [];


    public function getNodeIdentifier()
    {
        return ($c = $this->getCurrentStackFrame()) ? $c->getCycle()->nodeIdentifier : NULL;
    }

    public function getNodeAttributes(): ?array
    {
        return ($c = $this->getCurrentStackFrame()) ? $c->getCycle()->nodeAttributes : NULL;
    }

    public function markAsUpdated(int $updateState)
    {
        $cp = $nd = NULL;
        if($updateState & self::UPDATE_STATE_COMPONENT)
            $cp = ($c = $this->getCurrentStackFrame()) ? $c->getCycle()->nodeComponentName : NULL;
        if($updateState & self::UPDATE_STATE_NODE)
            $nd = ($c = $this->getCurrentStackFrame()) ? $c->getCycle()->nodeIdentifier : NULL;

        if($cp || $nd) {
            if($updateState & self::UPDATE_STATE_CURRENT_CYCLE) {
                if($root = $this->getCurrentStackFrame()) {
                    if($nd && !in_array($nd, $root->updatedNodes))
                        $root->updatedNodes[] = $nd;
                    if($cp && !in_array($cp, $root->updatedComponents))
                        $root->updatedComponents[] = $cp;
                }
            } elseif($updateState & self::UPDATE_STATE_ROOT_CYCLE) {
                $root = $this->getCurrentStackFrame();
                while ($c = $root->parentFrame) {
                    $root = $c;
                }

                if($root) {
                    if($nd && !in_array($nd, $root->updatedNodes))
                        $root->updatedNodes[] = $nd;
                    if($cp && !in_array($cp, $root->updatedComponents))
                        $root->updatedComponents[] = $cp;
                }
            } elseif($updateState & self::UPDATE_STATE_FOREVER) {
                if($cp && !in_array($cp, $this->foreverComponents))
                    $this->foreverComponents[] = $cp;
                if($nd && !in_array($nd, $this->foreverNodes))
                    $this->foreverNodes[] = $nd;
            }
        }
    }

    public function getRequestedOutputSocketName(): ?string
    {
        return ($c = $this->getCurrentStackFrame()) ? $c->getCycle()->requestedSocketName : NULL;
    }

    public function getTriggeredSocketName(): ?string
    {
        return ($c = $this->getCurrentStackFrame()) ? $c->getCycle()->triggeredSocketName : NULL;
    }

    public function pushStackFrame(_StackFrame $newFrame) {
        if($c = $this->getCurrentStackFrame()) {
            $newFrame->parentFrame = $c;
            $c->nextFrame = $newFrame;
        }
        $this->stackFrames[] = $newFrame;
    }

    public function getCurrentStackFrame(): ?_StackFrame {
        return end($this->stackFrames) ?: NULL;
    }

    public function popStackFrame() {
        array_pop($this->stackFrames);
        if($c = $this->getCurrentStackFrame()) {
            $c->nextFrame = NULL;
        }
    }

    public function needsUpdate($nodeID, $componentName) {
        $sf = $this->getCurrentStackFrame();

        if(in_array($nodeID, $this->foreverNodes) || in_array($nodeID, $sf->updatedNodes))
            return false;

        if(in_array($componentName, $this->foreverComponents) || in_array($componentName, $sf->updatedComponents))
            return false;

        return true;
    }
}