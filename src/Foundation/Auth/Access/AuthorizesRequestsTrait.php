<?php

namespace Nova\Foundation\Auth\Access;

use Nova\Auth\Access\GateInterface as Gate;
use Nova\Auth\Access\UnauthorizedException;
use Nova\Support\Facades\App;

use Symfony\Component\HttpKernel\Exception\HttpException;


trait AuthorizesRequestsTrait
{
    /**
     * Authorize a given action against a set of arguments.
     *
     * @param  mixed  $ability
     * @param  mixed|array  $arguments
     * @return \Nova\Auth\Access\Response
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function authorize($ability, $arguments = array())
    {
        list($ability, $arguments) = $this->parseAbilityAndArguments($ability, $arguments);

        $gate = App::make(Gate::class);

        return $this->authorizeAtGate($gate, $ability, $arguments);
    }

    /**
     * Authorize a given action for a user.
     *
     * @param  \Nova\Auth\UserInterface|mixed  $user
     * @param  mixed  $ability
     * @param  mixed|array  $arguments
     * @return \Nova\Auth\Access\Response
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function authorizeForUser($user, $ability, $arguments = array())
    {
        list($ability, $arguments) = $this->parseAbilityAndArguments($ability, $arguments);

        $gate = App::make(Gate::class)->forUser($user);

        return $this->authorizeAtGate($gate, $ability, $arguments);
    }

    /**
     * Authorize the request at the given gate.
     *
     * @param  \Nova\Auth\Access\GateInterface  $gate
     * @param  mixed  $ability
     * @param  mixed|array  $arguments
     * @return \Nova\Auth\Access\Response
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function authorizeAtGate(Gate $gate, $ability, $arguments)
    {
        try {
            return $gate->authorize($ability, $arguments);
        }
        catch (UnauthorizedException $e) {
            throw $this->createGateUnauthorizedException(
                $ability, $arguments, $e->getMessage(), $e
            );
        }
    }

    /**
     * Guesses the ability's name if it wasn't provided.
     *
     * @param  mixed  $ability
     * @param  mixed|array  $arguments
     * @return array
     */
    protected function parseAbilityAndArguments($ability, $arguments)
    {
        if (is_string($ability)) {
            return array($ability, $arguments);
        }

        list(,, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        return array($caller['function'], $ability);
    }

    /**
     * Throw an unauthorized exception based on gate results.
     *
     * @param  string  $ability
     * @param  mixed|array  $arguments
     * @param  string  $message
     * @param  \Exception  $previousException
     * @return \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function createGateUnauthorizedException($ability, $arguments, $message = null, $previousException = null)
    {
        $message = $message ?: __d('nova', 'This action is unauthorized.');

        return new HttpException(403, $message, $previousException);
    }
}