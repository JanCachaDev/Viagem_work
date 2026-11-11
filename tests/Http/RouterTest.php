<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = (new Router())
            ->get('/tiles/{z}/{x}/{y}.pbf', fn (array $p) => Response::json($p))
            ->get('/api/parcels/{id}', fn (array $p) => Response::json($p));
    }

    public function testExtractsNumericParameters(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/tiles/14/8891/5524.pbf'));

        self::assertSame('{"z":14,"x":8891,"y":5524}', $response->body);
    }

    public function testNonNumericParameterIsNotFound(): void
    {
        $this->assertStatus(404, new Request('GET', '/api/parcels/1;DROP'));
        $this->assertStatus(404, new Request('GET', '/api/parcels/../etc'));
        $this->assertStatus(404, new Request('GET', '/tiles/1/2/3.png'));
    }

    public function testWrongMethod(): void
    {
        $this->assertStatus(405, new Request('POST', '/api/parcels/1'));
    }

    public function testHeadIsAllowedForGetRoutes(): void
    {
        self::assertSame(200, $this->router->dispatch(new Request('HEAD', '/api/parcels/1'))->status);
    }

    private function assertStatus(int $status, Request $request): void
    {
        try {
            $this->router->dispatch($request);
            self::fail('Očekávána HttpException');
        } catch (HttpException $e) {
            self::assertSame($status, $e->status);
        }
    }
}
