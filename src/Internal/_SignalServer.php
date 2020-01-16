<?php

namespace Ikarus\Logic\Internal;


use Ikarus\Logic\Model\Executable\Context\SignalServerInterface;

class _SignalServer extends _ValuesServer implements SignalServerInterface
{
    public $signalForwarder;
    public $exposedSignals;

    public function forwardSignal(string $outputSocketName)
    {
        call_user_func($this->signalForwarder, $outputSocketName);
    }

    public function exposeSignal(string $socketName) {
        if($this->exposedSignals)
            ($this->exposedSignals)($socketName);
    }
}