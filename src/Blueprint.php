<?php
/**
 * @author: RunnerLee
 * @email: runnerleer@gmail.com
 * @time: 2018-02
 */

namespace Runner\Heshen;

use Closure;
use Runner\Heshen\Event\Event;
use Runner\Heshen\Exceptions\LogicException;
use Runner\Heshen\Exceptions\StateNotFoundException;
use Runner\Heshen\Exceptions\TransitionNotFoundException;
use Runner\Heshen\Support\StateEvents;
use Runner\Heshen\Support\Str;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Blueprint
{
    /**
     * @var State[]
     */
    protected $states = [];

    /**
     * @var Transition[]
     */
    protected $transitions = [];

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    /**
     * @var string
     */
    protected $name = '';

    /**
     * Blueprint constructor.
     */
    public function __construct()
    {
        $this->dispatcher = new EventDispatcher();

        $this->configure();

        method_exists($this, 'postInitial') && $this->eventListener('postInitial');
    }

    /**
     * @param $name
     *
     * @return Transition
     */
    public function getTransition(string $name): Transition
    {
        if (array_key_exists($name, $this->transitions)) {
            return $this->transitions[$name];
        }

        throw new TransitionNotFoundException($name);
    }

    /**
     * @param $name
     *
     * @return State
     */
    public function getState(string $name): State
    {
        if (array_key_exists($name, $this->states)) {
            return $this->states[$name];
        }

        throw new StateNotFoundException($name);
    }

    /**
     * @return EventDispatcher
     */
    public function getDispatcher(): EventDispatcher
    {
        return $this->dispatcher;
    }

    /**
     * @param string $name
     *
     * @return Blueprint
     */
    protected function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param string $name
     * @param string $type
     *
     * @return Blueprint
     */
    protected function addState(string $name, string $type): self
    {
        $this->states[$name] = new State($name, $type);

        return $this;
    }

    /**
     * @param string $name
     * @param string $from
     * @param string $to
     * @param null   $checker
     *
     * @return $this
     */
    protected function addTransition(string $name, string $from, string $to, $checker = null): self
    {
        $this->transitions[$name] = new Transition(
            $name,
            $this->getState($from),
            $this->getState($to),
            $checker
        );

        $preMethod = Str::studly("pre{$name}");
        $postMethod = Str::studly("post{$name}");

        if (method_exists($this, $preMethod)) {
            $this->dispatcher->addListener(
                StateEvents::PRE_TRANSITION.$name,
                $this->eventListener($preMethod)
            );
        }

        if (method_exists($this, $postMethod)) {
            $this->dispatcher->addListener(
                StateEvents::POST_TRANSITION.$name,
                $this->eventListener($postMethod)
            );
        }

        return $this;
    }

    /**
     * @param $method
     *
     * @return Closure
     */
    protected function eventListener($method): Closure
    {
        return function (Event $event) use ($method) {
            return call_user_func([$this, $method], $event->getStateful(), $event->getParameters());
        };
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        throw new LogicException('you must overwrite the configure method in the concrete blueprint class');
    }
}
