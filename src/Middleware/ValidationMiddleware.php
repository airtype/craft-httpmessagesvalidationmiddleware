<?php

namespace HttpMessagesValidationMiddleware\Middleware;

use HttpMessages\Http\CraftRequest as Request;
use HttpMessages\Http\CraftResponse as Response;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;
use HttpMessages\Exceptions\HttpMessagesException;

class ValidationMiddleware
{
    /**
     * Invoke
     *
     * @return void
     */
    public function __invoke(Request $request, Response $response, callable $next)
    {
        $config = $request->getRoute()->getMiddlewareConfig('validation');
        $input = ($request->getMethod() === 'GET') ? $request->getQueryParams() : $request->getParams();

        try {
            $validator = $this->getValidator($config);

            $validator->assert($input);
        } catch(NestedValidationException $validation_exception) {
            $exception = new HttpMessagesException('Whoops, looks like something is missing!');

            $exception->setErrors($validation_exception->getMessages());

            $exception->setInput($input);

            throw $exception;
        }

        $response = $next($request, $response);

        return $response;
    }

    private function getValidator(array $config)
    {
        $validator = new v;

        foreach ($config as $input => $rules) {
            $rules = explode('|', $rules);

            foreach ($rules as $rule) {
                $optional = false;

                if ($this->isOptional($rule)) {
                    $rule = substr($rule, 1);

                    $optional = true;
                }

                if ($arguments = $this->getArguments($rule, $optional)) {
                    if ($optional) {
                        $validator->key($input, call_user_func_array('\\Respect\\Validation\\Validator::' . $arguments['rule'], $arguments['arguments']), false);
                    } else {
                        $validator->key($input, call_user_func_array('\\Respect\\Validation\\Validator::' . $arguments['rule'], $arguments['arguments']));
                    }
                } else {
                    if ($optional) {
                        $validator->key($input, v::$rule(), false);
                    } else {
                        $validator->key($input, v::$rule());
                    }
                }
            }
        }

        return $validator;
    }

    private function isOptional($rule)
    {
        return ($rule[0] === '?');
    }

    private function getArguments($rule)
    {
        $regex = '#(.*)\((([^()]+|(?R))*)\)#';

        if (preg_match_all($regex, $rule, $matches)) {
            $rule = $matches[1][0];
            $arguments = $matches[2][0];

            $arguments = array_map('trim', explode(',', $arguments));

            return [
                'rule'      => $rule,
                'arguments' => $arguments,
            ];
        }

        return null;
    }

}
