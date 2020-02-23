<?php

namespace Lather;

use Exception;
use SoapClient;
use JsonSerializable;
use Lather\Macros\Filterable;

class Lather implements JsonSerializable
{
    use Filterable;

    /**
     * Soap Client.
     *
     * @var SoapClient
     */
    protected $client;

    /**
     * WSDL URL.
     *
     * @var string
     */
    protected $wsdl;

    /**
     * Array of soap params.
     *
     * @var array
     */
    protected $params;

    /**
     * Array of casts for returned responses.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Array of runtime macros.
     *
     * @var array
     */
    private $runtimeMacros = [];

    /**
     * Array containing the formatted reponse.
     *
     * @var array
     */
    private $formattedResponse = [];

    public function __construct()
    {
        $this->client = new SoapClient($this->wsdl);

        $this->addRuntimeMacros();
    }

    /**
     * Boot any runtime macros that have a function starting with 'boot' e.g. bootRest().
     */
    protected function addRuntimeMacros()
    {
        // There are probably much cleaner ways of doing this!
        $classMethods = get_class_methods($this);
        $methods = get_class_methods(Lather::class);

        $methods = array_unique(array_merge($classMethods, $methods));

        foreach ($methods as $method) {
            if (strpos($method, 'boot') === 0) {
                if (!in_array($method, $this->runtimeMacros)) {
                    $this->runtimeMacros[] = $method;
                } else {
                    throw new Exception("Runtime macro: {$method} already defined");
                }
            }
        }
    }

    /**
     * Returns the class name as a string for the SOAP call.
     *
     * @return string
     */
    protected function getClassName(): string
    {
        return (string) get_class($this);
    }

    protected function formatResponse($response)
    {
        foreach ($response as $key => $value) {
            if (array_key_exists($key, $this->casts)) {
                $this->formattedResponse[$key] = $this->applyCastsToValue($key, $value);
            } else {
                $this->formattedResponse[$key] = $value;
            }
        }

        $this->applyMacros();

        return $this->formattedResponse;
    }

    /**
     * Apply any runtime macros to the SOAP response.
     */
    private function applyMacros()
    {
        foreach ($this->runtimeMacros as $macro) {
            $this->formattedResponse = call_user_func([$this, $macro], $this->formattedResponse);
        }
    }

    /**
     * Apply any casts set by the user for a specified value.
     *
     * @param string $key
     * @param mixed  $value
     */
    protected function applyCastsToValue($key, $value)
    {
        $castValue = $this->casts[$key];

        switch ($castValue) {
            case 'integer':
            case 'int':
                return (int) $value;
                break;
            case 'string':
                return (string) $value;
                break;
            default:
                return $value;
        }
    }

    /**
     * Call the SOAP function.
     *
     * @param mixed ...$params
     */
    public function call(...$params)
    {
        foreach ($params as $param) {
            foreach ($param as $pKey => $pValue) {
                if (array_key_exists($pKey, $this->params)) {
                    $preppedParam[$pKey] = $pValue;
                } else {
                    return new Exception("Param: {$pKey} does not exist");
                }
            }
        }

        $soapFunction = $this->getClassName();

        $soapResp = $this->client->__soapCall($soapFunction, [$preppedParam]);

        return $this->formatResponse($soapResp);
    }

    public function __get($name)
    {
        return $this->formattedResponse[$name];
    }

    public function __call($name, $arguments)
    {
        if (method_exists($this, 'macro'.ucfirst($name))) {
            return call_user_func([$this, 'macro'.ucfirst($name)], ...$arguments);
        } else {
            throw new Exception("Method: {$name} does not exist");
        }
    }

    public function all()
    {
        return $this->formattedResponse;
    }

    public function jsonSerialize()
    {
        return $this->formattedResponse;
    }
}
