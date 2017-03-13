<?php

namespace Nova\Routing;

use Nova\Http\Request;
use Nova\Http\Response;
use Nova\Events\Dispatcher;
use Nova\Container\Container;
use Nova\Pipeline\Pipeline;
use Nova\Support\Collection;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Closure;


class Router
{
    /**
     * The event dispatcher instance.
     *
     * @var \Nova\Events\Dispatcher
     */
    protected $events;

    /**
     * The IoC container instance.
     *
     * @var \Nova\Container\Container
     */
    protected $container;

    /**
     * The route collection instance.
     *
     * @var \Nova\Routing\RouteCollection
     */
    protected $routes;

    /**
     * The currently dispatched route instance.
     *
     * @var \Nova\Routing\Route
     */
    protected $current;

    /**
     * The request currently being dispatched.
     *
     * @var \Nova\Http\Request
     */
    protected $currentRequest;

    /**
     * All of the short-hand keys for middlewares.
     *
     * @var array
     */
    protected $middleware = array();

    /**
     * The controller inspector instance.
     *
     * @var \Nova\Routing\ControllerInspector
     */
    protected $inspector;

    /**
     * The registered route value binders.
     *
     * @var array
     */
    protected $binders = array();

    /**
     * The globally available parameter patterns.
     *
     * @var array
     */
    protected $patterns = array();

    /**
     * The route group attribute stack.
     *
     * @var array
     */
    protected $groupStack = array();

    /**
     * All of the verbs supported by the router.
     *
     * @var array
     */
    public static $verbs = array('GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS');

    /**
     * The resource registrar instance.
     *
     * @var \Nova\Routing\ResourceRegistrar
     */
    protected $registrar;


    /**
     * Create a new Router instance.
     *
     * @param  \Nova\Events\Dispatcher  $events
     * @param  \Nova\Container\Container  $container
     * @return void
     */
    public function __construct(Dispatcher $events, Container $container = null)
    {
        $this->events = $events;

        $this->routes = new RouteCollection;

        $this->container = $container ?: new Container;

        $this->bind('_missing', function($v)
        {
            return explode('/', $v);
        });
    }

    /**
     * Register a new GET route with the router.
     *
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Nova\Routing\Route
     */
    public function get($uri, $action)
    {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }

    /**
     * Register a new POST route with the router.
     *
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Nova\Routing\Route
     */
    public function post($uri, $action)
    {
        return $this->addRoute('POST', $uri, $action);
    }

    /**
     * Register a new PUT route with the router.
     *
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Nova\Routing\Route
     */
    public function put($uri, $action)
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    /**
     * Register a new PATCH route with the router.
     *
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Nova\Routing\Route
     */
    public function patch($uri, $action)
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    /**
     * Register a new DELETE route with the router.
     *
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Nova\Routing\Route
     */
    public function delete($uri, $action)
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    /**
     * Register a new OPTIONS route with the router.
     *
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Nova\Routing\Route
     */
    public function options($uri, $action)
    {
        return $this->addRoute('OPTIONS', $uri, $action);
    }

    /**
     * Register a new route responding to all verbs.
     *
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Nova\Routing\Route
     */
    public function any($uri, $action)
    {
        $verbs = array('GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE');

        return $this->addRoute($verbs, $uri, $action);
    }

    /**
     * Register a new route with the given verbs.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Nova\Routing\Route
     */
    public function match($methods, $uri, $action)
    {
        return $this->addRoute(array_map('strtoupper', (array) $methods), $uri, $action);
    }

    /**
     * Register an array of controllers with wildcard routing.
     *
     * @param  array  $controllers
     * @return void
     */
    public function controllers(array $controllers)
    {
        foreach ($controllers as $uri => $name) {
            $this->controller($uri, $name);
        }
    }

    /**
     * Route a controller to a URI with wildcard routing.
     *
     * @param  string  $uri
     * @param  string  $controller
     * @param  array   $names
     * @return void
     */
    public function controller($uri, $controller, $names = array())
    {
        $prepended = $controller;

        // First, we will check to see if a controller prefix has been registered in
        // the route group. If it has, we will need to prefix it before trying to
        // reflect into the class instance and pull out the method for routing.
        if (! empty($this->groupStack)) {
            $prepended = $this->prependGroupUses($controller);
        }

        $routable = $this->getInspector()->getRoutable($prepended, $uri);

        // When a controller is routed using this method, we use Reflection to parse
        // out all of the routable methods for the controller, then register each
        // route explicitly for the developers, so reverse routing is possible.
        foreach ($routable as $method => $routes) {
            foreach ($routes as $route) {
                $this->registerInspected($route, $controller, $method, $names);
            }
        }

        $this->addFallthroughRoute($controller, $uri);
    }

    /**
     * Register an inspected controller route.
     *
     * @param  array   $route
     * @param  string  $controller
     * @param  string  $method
     * @param  array   $names
     * @return void
     */
    protected function registerInspected($route, $controller, $method, &$names)
    {
        $action = array('uses' => $controller.'@'.$method);

        // If a given controller method has been named, we will assign the name to the
        // controller action array, which provides for a short-cut to method naming
        // so you don't have to define an individual route for these controllers.
        $action['as'] = array_get($names, $method);

        $this->{$route['verb']}($route['uri'], $action);
    }

    /**
     * Add a fallthrough route for a controller.
     *
     * @param  string  $controller
     * @param  string  $uri
     * @return void
     */
    protected function addFallthroughRoute($controller, $uri)
    {
        $missing = $this->any($uri.'/{_missing}', $controller.'@missingMethod');

        $missing->where('_missing', '(.*)');
    }

    /**
     * Route a resource to a controller.
     *
     * @param  string  $name
     * @param  string  $controller
     * @param  array   $options
     * @return void
     */
    public function resource($name, $controller, array $options = array())
    {
        $registrar = $this->getRegistrar();

        $registrar->register($name, $controller, $options);
    }

    /**
     * Create a route group with shared attributes.
     *
     * @param  array     $attributes
     * @param  \Closure  $callback
     * @return void
     */
    public function group(array $attributes, Closure $callback)
    {
        $this->updateGroupStack($attributes);

        // Once we have updated the group stack, we will execute the user Closure and
        // merge in the groups attributes when the route is created. After we have
        // run the callback, we will pop the attributes off of this group stack.
        call_user_func($callback, $this);

        array_pop($this->groupStack);
    }

    /**
     * Update the group stack with the given attributes.
     *
     * @param  array  $attributes
     * @return void
     */
    protected function updateGroupStack(array $attributes)
    {
        if (! empty($this->groupStack)) {
            $old = last($this->groupStack);

            $attributes = static::mergeGroup($attributes, $old);
        }

        $this->groupStack[] = $attributes;
    }

    /**
     * Merge the given array with the last group stack.
     *
     * @param  array  $new
     * @return array
     */
    public function mergeWithLastGroup($new)
    {
        $old = last($this->groupStack);

        return static::mergeGroup($new, $old);
    }

    /**
     * Merge the given group attributes.
     *
     * @param  array  $new
     * @param  array  $old
     * @return array
     */
    public static function mergeGroup($new, $old)
    {
        $new['namespace'] = static::formatUsesPrefix($new, $old);

        $new['prefix'] = static::formatGroupPrefix($new, $old);

        if (isset($new['domain'])) {
            unset($old['domain']);
        }

        $new['where'] = array_merge(
            isset($old['where']) ? $old['where'] : array(),
            isset($new['where']) ? $new['where'] : array()
        );

        if (isset($old['as'])) {
            $new['as'] = $old['as'] .(isset($new['as']) ? $new['as'] : '');
        }

        return array_merge_recursive(array_except($old, array('namespace', 'prefix', 'where', 'as')), $new);
    }

    /**
     * Format the uses prefix for the new group attributes.
     *
     * @param  array  $new
     * @param  array  $old
     * @return string
     */
    protected static function formatUsesPrefix($new, $old)
    {
        if (isset($new['namespace'])) {
            return isset($old['namespace'])
                ? trim($old['namespace'], '\\') .'\\' .trim($new['namespace'], '\\')
                : trim($new['namespace'], '\\');
        }

        return isset($old['namespace']) ? $old['namespace'] : null;
    }

    /**
     * Format the prefix for the new group attributes.
     *
     * @param  array  $new
     * @param  array  $old
     * @return string
     */
    protected static function formatGroupPrefix($new, $old)
    {
        $oldPrefix = isset($old['prefix']) ? $old['prefix'] : null;

        if (isset($new['prefix'])) {
            return trim($oldPrefix, '/') .'/' .trim($new['prefix'], '/');
        }

        return $oldPrefix;
    }

    /**
     * Get the prefix from the last group on the stack.
     *
     * @return string
     */
    protected function getLastGroupPrefix()
    {
        if (! empty($this->groupStack)) {
            $last = end($this->groupStack);

            return isset($last['prefix']) ? $last['prefix'] : '';
        }

        return '';
    }

    /**
     * Add a route to the underlying route collection.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  \Closure|array|string  $action
     * @return \Nova\Routing\Route
     */
    protected function addRoute($methods, $uri, $action)
    {
        return $this->routes->add($this->createRoute($methods, $uri, $action));
    }

    /**
     * Create a new route instance.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  mixed   $action
     * @return \Nova\Routing\Route
     */
    protected function createRoute($methods, $uri, $action)
    {
        // If the route is routing to a controller we will parse the route action into
        // an acceptable array format before registering it and creating this route
        // instance itself. We need to build the Closure that will call this out.
        if ($this->actionReferencesController($action)) {
            $action = $this->convertToControllerAction($action);
        }

        $route = $this->newRoute(
            $methods, $uri = $this->prefix($uri), $action
        );

        // If we have groups that need to be merged, we will merge them now after this
        // route has already been created and is ready to go. After we're done with
        // the merge we will be ready to return the route back out to the caller.
        if (! empty($this->groupStack)) {
            $this->mergeGroupAttributesIntoRoute($route);
        }

        $this->addWhereClausesToRoute($route);

        return $route;
    }

    /**
     * Create a new Route object.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  mixed   $action
     * @return \Nova\Routing\Route
     */
    protected function newRoute($methods, $uri, $action)
    {
        return (new Route($methods, $uri, $action))->setContainer($this->container);
    }

    /**
     * Prefix the given URI with the last prefix.
     *
     * @param  string  $uri
     * @return string
     */
    protected function prefix($uri)
    {
        return trim(trim($this->getLastGroupPrefix(), '/').'/'.trim($uri, '/'), '/') ?: '/';
    }

    /**
     * Add the necessary where clauses to the route based on its initial registration.
     *
     * @param  \Nova\Routing\Route  $route
     * @return \Nova\Routing\Route
     */
    protected function addWhereClausesToRoute($route)
    {
        $route->where(
            array_merge($this->patterns, array_get($route->getAction(), 'where', []))
        );

        return $route;
    }

    /**
     * Merge the group stack with the controller action.
     *
     * @param  \Nova\Routing\Route  $route
     * @return void
     */
    protected function mergeGroupAttributesIntoRoute($route)
    {
        $action = $this->mergeWithLastGroup($route->getAction());

        $route->setAction($action);
    }

    /**
     * Determine if the action is routing to a controller.
     *
     * @param  array  $action
     * @return bool
     */
    protected function actionReferencesController($action)
    {
        if ($action instanceof Closure) {
            return false;
        }

        return is_string($action) || (isset($action['uses']) && is_string($action['uses']));
    }

    /**
     * Add a controller based route action to the action array.
     *
     * @param  array|string  $action
     * @return array
     */
    protected function convertToControllerAction($action)
    {
        if (is_string($action)) $action = array('uses' => $action);

        // Here we'll get an instance of this controller dispatcher and hand it off to
        // the Closure so it will be used to resolve the class instances out of our
        // IoC container instance and call the appropriate methods on the class.
        if (! empty($this->groupStack)) {
            $action['uses'] = $this->prependGroupUses($action['uses']);
        }

        // Here we'll get an instance of this controller dispatcher and hand it off to
        // the Closure so it will be used to resolve the class instances out of our
        // IoC container instance and call the appropriate methods on the class.
        $action['controller'] = $action['uses'];

        return $action;
    }

    /**
     * Prepend the last group uses onto the use clause.
     *
     * @param  string  $uses
     * @return string
     */
    protected function prependGroupUses($uses)
    {
        $group = last($this->groupStack);

        return isset($group['namespace']) ? $group['namespace'].'\\'.$uses : $uses;
    }

    /**
     * Dispatch the request to the application.
     *
     * @param  \Nova\Http\Request  $request
     * @return \Nova\Http\Response
     */
    public function dispatch(Request $request)
    {
        $this->currentRequest = $request;

        $response = $this->dispatchToRoute($request);

        $response = $this->prepareResponse($request, $response);

        return $response;
    }

    /**
     * Dispatch the request to a route and return the response.
     *
     * @param  \Nova\Http\Request  $request
     * @return mixed
     */
    public function dispatchToRoute(Request $request)
    {
        // First we will find a route that matches this request. We will also set the
        // route resolver on the request so middlewares assigned to the route will
        // receive access to this route instance for checking of the parameters.
        $route = $this->findRoute($request);

        $request->setRouteResolver(function() use ($route)
        {
            return $route;
        });

        $this->events->fire('router.matched', array($route, $request));

        $response = $this->runRouteWithinStack($route, $request);

        return $this->prepareResponse($request, $response);
    }

    /**
     * Run the given route within a Stack "onion" instance.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    protected function runRouteWithinStack(Route $route, Request $request)
    {
        $shouldSkipMiddleware = $this->container->bound('middleware.disable') &&
                                ($this->container->make('middleware.disable') === true);

        $middleware = $shouldSkipMiddleware ? array() : $this->gatherRouteMiddlewares($route);

        // Create a Pipeline instance.
        $pipeline = new Pipeline($this->container);

        return $pipeline->send($request)
            ->through($middleware)
            ->then(function ($request) use ($route)
            {
                $response = $route->run($request);

                return $this->prepareResponse($request, $response);
            });
    }

    /**
     * Gather the middleware for the given route.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return array
     */
    public function gatherRouteMiddlewares(Route $route)
    {
        return Collection::make($route->middleware())->map(function ($name)
        {
            $middleware = $this->resolveMiddleware($name);

            return array($middleware);

        })->collapse()->all();
    }

    /**
     * Resolve the middleware name to a class name preserving passed parameters.
     *
     * @param  string  $name
     * @return string
     */
    public function resolveMiddleware($name)
    {
        $map = $this->middleware;

        list($name, $parameters) = array_pad(explode(':', $name, 2), 2, null);

        // Adjust the name and the parameters.
        if (isset($map[$name])) {
            $name = $map[$name];
        }

        if ($name instanceof Closure) {
            return array('callback' => $name, 'parameters' => $parameters);
        }

        $parameters = ! is_null($parameters) ? ':' .$parameters : '';

        return $name .$parameters;
    }

    /**
     * Find the route matching a given request.
     *
     * @param  \Nova\Http\Request  $request
     * @return \Nova\Routing\Route
     */
    protected function findRoute($request)
    {
        $this->current = $route = $this->routes->match($request);

        return $this->substituteBindings($route);
    }

    /**
     * Substitute the route bindings onto the route.
     *
     * @param  \Nova\Routing\Route  $route
     * @return \Nova\Routing\Route
     */
    protected function substituteBindings($route)
    {
        foreach ($route->parameters() as $key => $value) {
            if (isset($this->binders[$key])) {
                $route->setParameter($key, $this->performBinding($key, $value, $route));
            }
        }

        return $route;
    }

    /**
     * Call the binding callback for the given key.
     *
     * @param  string  $key
     * @param  string  $value
     * @param  \Nova\Routing\Route  $route
     * @return mixed
     */
    protected function performBinding($key, $value, $route)
    {
        return call_user_func($this->binders[$key], $value, $route);
    }

    /**
     * Register a route matched event listener.
     *
     * @param  string|callable  $callback
     * @return void
     */
    public function matched($callback)
    {
        $this->events->listen('router.matched', $callback);
    }

    /**
     * Get all of the defined middleware short-hand names.
     *
     * @return array
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }

    /**
     * Register a short-hand name for a middleware.
     *
     * @param  string  $name
     * @param  string|\Closure  $middleware
     * @return $this
     */
    public function middleware($name, $middleware)
    {
        $this->middleware[$name] = $middleware;

        return $this;
    }

    /**
     * Register a model binder for a wildcard.
     *
     * @param  string  $key
     * @param  string  $class
     * @param  \Closure  $callback
     * @return void
     *
     * @throws NotFoundHttpException
     */
    public function model($key, $class, Closure $callback = null)
    {
        $this->bind($key, function($value) use ($class, $callback)
        {
            if (is_null($value)) return null;

            // For model binders, we will attempt to retrieve the models using the find
            // method on the model instance. If we cannot retrieve the models we'll
            // throw a not found exception otherwise we will return the instance.
            if ($model = (new $class)->find($value)) {
                return $model;
            }

            // If a callback was supplied to the method we will call that to determine
            // what we should do when the model is not found. This just gives these
            // developer a little greater flexibility to decide what will happen.
            if ($callback instanceof Closure) {
                return call_user_func($callback);
            }

            throw new NotFoundHttpException;
        });
    }

    /**
     * Add a new route parameter binder.
     *
     * @param  string  $key
     * @param  string|callable  $binder
     * @return void
     */
    public function bind($key, $binder)
    {
        if (is_string($binder)) {
            $binder = $this->createClassBinding($binder);
        }

        $key = str_replace('-', '_', $key);

        $this->binders[$key] = $binder;
    }

    /**
     * Create a class based binding using the IoC container.
     *
     * @param  string    $binding
     * @return \Closure
     */
    public function createClassBinding($binding)
    {
        return function($value, $route) use ($binding)
        {
            // If the binding has an @ sign, we will assume it's being used to delimit
            // the class name from the bind method name. This allows for bindings
            // to run multiple bind methods in a single class for convenience.
            $segments = explode('@', $binding);

            $method = count($segments) == 2 ? $segments[1] : 'bind';

            $callable = array($this->container->make($segments[0]), $method);

            return call_user_func($callable, $value, $route);
        };
    }

    /**
     * Set a global where pattern on all routes
     *
     * @param  string  $key
     * @param  string  $pattern
     * @return void
     */
    public function pattern($key, $pattern)
    {
        $this->patterns[$key] = $pattern;
    }

    /**
     * Set a group of global where patterns on all routes
     *
     * @param  array  $patterns
     * @return void
     */
    public function patterns($patterns)
    {
        foreach ($patterns as $key => $pattern) {
            $this->pattern($key, $pattern);
        }
    }

    /**
     * Create a response instance from the given value.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  mixed  $response
     * @return \Nova\Http\Response
     */
    public function prepareResponse($request, $response)
    {
        if (! $response instanceof SymfonyResponse) {
            $response = new Response($response);
        }

        return $response->prepare($request);
    }

    /**
     * Determine if the router currently has a group stack.
     *
     * @return bool
     */
    public function hasGroupStack()
    {
        return ! empty($this->groupStack);
    }

    /**
     * Get the current group stack for the router.
     *
     * @return array
     */
    public function getGroupStack()
    {
        return $this->groupStack;
    }

    /**
     * Get a route parameter for the current route.
     *
     * @param  string  $key
     * @param  string  $default
     * @return mixed
     */
    public function input($key, $default = null)
    {
        return $this->current()->parameter($key, $default);
    }

    /**
     * Get the currently dispatched route instance.
     *
     * @return \Nova\Routing\Route
     */
    public function getCurrentRoute()
    {
        return $this->current();
    }

    /**
     * Get the currently dispatched route instance.
     *
     * @return \Nova\Routing\Route
     */
    public function current()
    {
        return $this->current;
    }

    /**
     * Check if a route with the given name exists.
     *
     * @param  string  $name
     * @return bool
     */
    public function has($name)
    {
        return $this->routes->hasNamedRoute($name);
    }

    /**
     * Get the current route name.
     *
     * @return string|null
     */
    public function currentRouteName()
    {
        return ($this->current()) ? $this->current()->getName() : null;
    }

    /**
     * Alias for the "currentRouteNamed" method.
     *
     * @param  mixed  string
     * @return bool
     */
    public function is()
    {
        foreach (func_get_args() as $pattern) {
            if (str_is($pattern, $this->currentRouteName())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the current route matches a given name.
     *
     * @param  string  $name
     * @return bool
     */
    public function currentRouteNamed($name)
    {
        return ($this->current()) ? $this->current()->getName() == $name : false;
    }

    /**
     * Get the current route action.
     *
     * @return string|null
     */
    public function currentRouteAction()
    {
        if (! $this->current()) return;

        $action = $this->current()->getAction();

        return isset($action['controller']) ? $action['controller'] : null;
    }

    /**
     * Alias for the "currentRouteUses" method.
     *
     * @param  mixed  string
     * @return bool
     */
    public function uses()
    {
        foreach (func_get_args() as $pattern) {
            if (str_is($pattern, $this->currentRouteAction())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the current route action matches a given action.
     *
     * @param  string  $action
     * @return bool
     */
    public function currentRouteUses($action)
    {
        return $this->currentRouteAction() == $action;
    }

    /**
     * Get the request currently being dispatched.
     *
     * @return \Nova\Http\Request
     */
    public function getCurrentRequest()
    {
        return $this->currentRequest;
    }

    /**
     * Get the underlying route collection.
     *
     * @return \Nova\Routing\RouteCollection
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Get a controller inspector instance.
     *
     * @return \Nova\Routing\ControllerInspector
     */
    public function getInspector()
    {
        return $this->inspector ?: $this->inspector = new ControllerInspector;
    }

    /**
     * Get a Resource Registrar instance.
     *
     * @return \Nova\Routing\ResourceRegistrar
     */
    public function getRegistrar()
    {
        return $this->registrar ?: $this->registrar = new ResourceRegistrar($this);
    }

    /**
     * Get the global "where" patterns.
     *
     * @return array
     */
    public function getPatterns()
    {
        return $this->patterns;
    }

}
