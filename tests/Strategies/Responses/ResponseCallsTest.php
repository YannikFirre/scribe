<?php

namespace Knuckles\Scribe\Tests\Strategies\Responses;

use Dingo\Api\Routing\Router;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Camel\Extraction\ResponseCollection;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Extracting\Strategies\Responses\ResponseCalls;
use Knuckles\Scribe\ScribeServiceProvider;
use Knuckles\Scribe\Tests\BaseLaravelTest;
use Knuckles\Scribe\Tests\Fixtures\TestController;
use Knuckles\Scribe\Tools\DocumentationConfig;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Illuminate\Support\Facades\Route as LaravelRouteFacade;

class ResponseCallsTest extends BaseLaravelTest
{
    use ArraySubsetAsserts;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scribe.database_connections_to_transact' => []]);
    }

    /** @test */
    public function can_call_route_and_fetch_response()
    {
        $route = LaravelRouteFacade::post('/shouldFetchRouteResponse', [TestController::class, 'shouldFetchRouteResponse']);

        $rules = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'response_calls' => [
                'methods' => ['*'],
            ],
        ];

        $strategy = new ResponseCalls(new DocumentationConfig([]));
        $results = $strategy->makeResponseCallIfConditionsPass(
            ExtractedEndpointData::fromRoute($route), $rules
        );

        $this->assertEquals(200, $results[0]['status']);
        $this->assertArraySubset([
            'id' => 4,
            'name' => 'banana',
            'color' => 'red',
            'weight' => '1 kg',
            'delicious' => true,
        ], json_decode($results[0]['content'], true));
    }

    /** @test */
    public function can_upload_file_parameters_in_response_calls()
    {
        $route = RouteFacade::post('/withFormDataParams', [TestController::class, 'withFormDataParams']);

        config(['scribe.routes.0.apply.response_calls.methods' => ['POST']]);
        $parsed = (new Extractor())->processRoute($route, config('scribe.routes.0.apply'));

        $responses = $parsed->responses->toArray();
        $this->assertCount(1, $responses);
        $this->assertArraySubset([
            "status" => 200,
            "description" => null,
            "content" => '{"filename":"scribe.php","filepath":"config","name":"cat.jpg"}',
        ], $responses[0]);
    }

    /** @test */
    public function uses_configured_settings_when_calling_route()
    {
        $route = LaravelRouteFacade::post('/echo/{id}', [TestController::class, 'shouldFetchRouteResponseWithEchoedSettings']);

        $rules = [
            'response_calls' => [
                'methods' => ['*'],
                'queryParams' => [
                    'queryParam' => 'queryValue',
                ],
                'bodyParams' => [
                    'bodyParam' => 'bodyValue',
                ],
            ],
        ];

        $endpointData = ExtractedEndpointData::fromRoute($route, [
            'auth' => ['headers', 'Authorization', 'Bearer bearerToken'],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'header' => 'value',
            ],
        ]);

        $strategy = new ResponseCalls(new DocumentationConfig([]));
        $results = $strategy->makeResponseCallIfConditionsPass($endpointData, $rules);

        $this->assertEquals(200, $results[0]['status']);

        $responseContent = json_decode($results[0]['content'], true);
        $this->assertEquals('queryValue', $responseContent['queryParam']);
        $this->assertEquals('bodyValue', $responseContent['bodyParam']);
        $this->assertEquals('value', $responseContent['header']);
        $this->assertEquals('Bearer bearerToken', $responseContent['auth']);
    }

    /** @test */
    public function can_override_application_config_during_response_call()
    {
        $route = LaravelRouteFacade::post('/echoesConfig', [TestController::class, 'echoesConfig']);

        $rules = [
            'response_calls' => [
                'methods' => ['*'],
            ],
        ];

        $strategy = new ResponseCalls(new DocumentationConfig([]));
        $results = $strategy->makeResponseCallIfConditionsPass(
            ExtractedEndpointData::fromRoute($route), $rules
        );
        $originalValue = json_decode($results[0]['content'], true)['app.env'];

        $now = time();
        $rules = [
            'response_calls' => [
                'methods' => ['*'],
                'config' => [
                    'app.env' => $now,
                ],
            ],
        ];

        $results = $strategy->makeResponseCallIfConditionsPass(
            ExtractedEndpointData::fromRoute($route), $rules
        );
        $newValue = json_decode($results[0]['content'], true)['app.env'];
        $this->assertEquals($now, $newValue);
        $this->assertNotEquals($originalValue, $newValue);
    }

    /**
     * @test
     * @group dingo
     */
    public function can_call_route_and_fetch_response_with_dingo()
    {
        $route = $this->registerDingoRoute('post', '/shouldFetchRouteResponse', 'shouldFetchRouteResponse');

        $rules = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'response_calls' => [
                'methods' => ['*'],
            ],
        ];

        $strategy = new ResponseCalls(new DocumentationConfig());
        $results = $strategy->makeResponseCallIfConditionsPass(
            ExtractedEndpointData::fromRoute($route), $rules
        );

        $this->assertEquals(200, $results[0]['status']);
        $this->assertArraySubset([
            'id' => 4,
            'name' => 'banana',
            'color' => 'red',
            'weight' => '1 kg',
            'delicious' => true,
        ], json_decode($results[0]['content'], true));
    }

    /**
     * @test
     * @group dingo
     */
    public function uses_configured_settings_when_calling_route_with_dingo()
    {
        $route = $this->registerDingoRoute('post', '/echo/{id}', 'shouldFetchRouteResponseWithEchoedSettings');

        $rules = [
            'response_calls' => [
                'methods' => ['*'],
                'queryParams' => [
                    'queryParam' => 'queryValue',
                ],
                'bodyParams' => [
                    'bodyParam' => 'bodyValue',
                ],
            ],
        ];

        $endpointData = ExtractedEndpointData::fromRoute($route, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'header' => 'value',
            ],
        ]);
        $strategy = new ResponseCalls(new DocumentationConfig());
        $results = $strategy->makeResponseCallIfConditionsPass($endpointData, $rules);

        $this->assertEquals(200, $results[0]['status']);

        $responseContent = json_decode($results[0]['content'], true);
        $this->assertEquals('queryValue', $responseContent['queryParam']);
        $this->assertEquals('bodyValue', $responseContent['bodyParam']);
        $this->assertEquals('value', $responseContent['header']);
    }

    /**
     * @test
     * @group dingo
     */
    public function can_override_application_config_during_response_call_with_dingo()
    {
        $route = $this->registerDingoRoute('post', '/echoesConfig', 'echoesConfig');

        $rules = [
            'response_calls' => [
                'methods' => ['*'],
            ],
        ];

        $strategy = new ResponseCalls(new DocumentationConfig());
        $results = $strategy->makeResponseCallIfConditionsPass(
            ExtractedEndpointData::fromRoute($route), $rules
        );
        $originalValue = json_decode($results[0]['content'], true)['app.env'];

        $now = time();
        $rules = [
            'response_calls' => [
                'methods' => ['*'],
                'config' => [
                    'app.env' => $now,
                ],
            ],
        ];

        $results = $strategy->makeResponseCallIfConditionsPass(
            ExtractedEndpointData::fromRoute($route), $rules
        );
        $newValue = json_decode($results[0]['content'], true)['app.env'];
        $this->assertEquals($now, $newValue);
        $this->assertNotEquals($originalValue, $newValue);
    }

    /** @test */
    public function does_not_make_response_call_if_success_response_already_gotten()
    {
        $route = LaravelRouteFacade::post('/shouldFetchRouteResponse', [TestController::class, 'shouldFetchRouteResponse']);

        $rules = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'response_calls' => [
                'methods' => ['*'],
            ],
        ];

        $endpointData = ExtractedEndpointData::fromRoute($route, [
            'responses' => new ResponseCollection([
                [
                    'status' => 200,
                    'content' => json_encode(['message' => 'LOL']),
                ],
            ]),
        ]);
        $strategy = new ResponseCalls(new DocumentationConfig([]));
        $results = $strategy->makeResponseCallIfConditionsPass($endpointData, $rules);

        $this->assertNull($results);
    }

    /** @test */
    public function does_not_make_response_call_if_forbidden_by_config()
    {
        $route = LaravelRouteFacade::post('/shouldFetchRouteResponse', [TestController::class, 'shouldFetchRouteResponse']);

        $rules = [
            'response_calls' => [
                'methods' => [],
            ],
        ];
        $strategy = new ResponseCalls(new DocumentationConfig([]));
        $results = $strategy->makeResponseCallIfConditionsPass(
            ExtractedEndpointData::fromRoute($route), $rules
        );

        $this->assertNull($results);
    }

    public function registerDingoRoute(string $httpMethod, string $path, string $controllerMethod)
    {
        $desiredRoute = null;
        /** @var Router $api */
        $api = app(Router::class);
        $api->version('v1', function (Router $api) use ($controllerMethod, $path, $httpMethod, &$desiredRoute) {
            $desiredRoute = $api->$httpMethod($path, [TestController::class, $controllerMethod]);
        });
        $routes = app(\Dingo\Api\Routing\Router::class)->getRoutes('v1');

        /*
         * Doing this bc we want an instance of Dingo\Api\Routing\Route, not Illuminate\Routing\Route, which the method above returns
         */
        return collect($routes)
            ->first(function (Route $route) use ($desiredRoute) {
                return $route->uri() === $desiredRoute->uri();
            });
    }
}
