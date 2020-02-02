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

namespace Ikarus\Logic;


use Ikarus\Logic\Data\DataInterface;
use Ikarus\Logic\Model\Component\ComponentModelInterface;
use Ikarus\Logic\ValueProvider\ValueProviderInterface;
use Throwable;

interface EngineInterface
{
    /**
     * Gets the used component model
     *
     * @return ComponentModelInterface
     */
    public function getModel(): ComponentModelInterface;

    /**
     * Binds the given data to the engine.
     *
     * NOTE: This must be done while the engine is not active or was terminated.
     *
     * @param DataInterface $data
     * @return bool
     */
    public function bindData(DataInterface $data): bool;

    /**
     * This method activates the engine and let you ask values from the logic or trigger signals into it.
     * NOTE: This method does not block the thread.
     */
    public function activate();

    /**
     * After activation, the engine remains in an idle mode, which means it is waiting for:
     * - A value gets requested
     * - A signal gets triggered
     *
     * For performance reasons both events are executed in a render cycle.
     * Node components get informed, if they already have updated a node in the same cycle
     */

    /**
     * Returns if the engine was activated or is idling.
     *
     * @return bool
     */
    public function isActive(): bool;

    /**
     * Terminates the engine.
     * This method tears down the engine and won't accept triggers or requests anymore.
     * It is still possible to activate it again.
     */
    public function terminate();


    /**
     * Begin a new render cycle
     * The render cycles are nested. So every time this method gets called, the engine will increase the cycle count.
     * Calling this method when the cycle count is zero, it will raise up a new cycle.
     * PLEASE NOTE: BEGINNING AND ENDING RENDER CYCLES MUST BE EQUAL!
     *
     * @see EngineInterface::endRenderCycle()
     */
    public function beginRenderCycle();

    /**
     * Ends a render cycle.
     * As you see above, this method reduces the cycle count.
     * Reaching zero, the render cycle gets terminated.
     * PLEASE NOTE: BEGINNING AND ENDING RENDER CYCLES MUST BE EQUAL!
     *
     * @see EngineInterface::beginRenderCycle()
     */
    public function endRenderCycle();

    /**
     * This method asks the logic for a value.
     * It will search for the node with $nodeIdentifier and its exposed socket.
     * If an exposed socket matches the passed $exposedSocketKey the engine will update the node and fetch the exposed value.
     * Fetching a value increases the render cycle.
     * So if you want to fetch several values at one time, begin a render cycle before and end it after! (Much better performance)
     *
     * @param string|int $nodeIdentifier
     * @param string $exposedSocketKey
     * @param ValueProviderInterface|null $valueProvider
     * @return mixed
     */
    public function requestValue($nodeIdentifier, string $exposedSocketKey, ValueProviderInterface $valueProvider = NULL);

    /**
     *  If you want all exposed values of a node, use this method instead of requestValue.
     * This method returns a list with all exposed values.
     *
     * @param $nodeIdentifier
     * @param ValueProviderInterface $valueProvider
     * @param null|Throwable $error
     * @return array|null
     */
    public function updateNode($nodeIdentifier, ValueProviderInterface $valueProvider, &$error = NULL);

    /**
     * Calling this method triggers a signal in the logic.
     * The signals are forward events and so every node of $componentName gets informed that a signal trigger on $exposedSocketKey (output socket) has occured.
     * Then it will follow the connections and inform the next node components and so on.
     * If you pass a specific node identifier, the signal gets only triggered for that node.
     *
     * @param string $componentName
     * @param string $exposedSocketKey
     * @param string|int|null $nodeIdentifier
     * @param ValueProviderInterface|null $valueProvider
     * @return TriggerResult
     */
    public function triggerSignal(string $exposedSocketKey, string $componentName = NULL, $nodeIdentifier = NULL, ValueProviderInterface $valueProvider = NULL);
}