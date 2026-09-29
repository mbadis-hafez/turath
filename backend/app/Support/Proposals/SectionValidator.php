<?php

namespace App\Support\Proposals;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;

/**
 * Runs a section payload through the same FormRequest the corresponding
 * endpoint uses, so a draft is validated exactly like the direct edit it
 * mirrors. The synthetic request is bound to a route whose parameter is named
 * after what each FormRequest reads (e.g. `route('artist')` for the
 * legacy_code unique rule).
 */
class SectionValidator
{
    /**
     * @param  class-string<FormRequest>  $class
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function validated(string $class, string $routeParameter, int|string $recordId, array $payload): array
    {
        return self::make($class, $routeParameter, $recordId, $payload)->validated();
    }

    /**
     * Validated data mapped onto flat model columns when the FormRequest
     * offers mappedAttributes(), the validated data otherwise.
     *
     * @param  class-string<FormRequest>  $class
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function mapped(string $class, string $routeParameter, int|string $recordId, array $payload): array
    {
        $request = self::make($class, $routeParameter, $recordId, $payload);

        return method_exists($request, 'mappedAttributes') ? $request->mappedAttributes() : $request->validated();
    }

    /**
     * @param  class-string<FormRequest>  $class
     * @param  array<string, mixed>  $payload
     */
    private static function make(string $class, string $routeParameter, int|string $recordId, array $payload): FormRequest
    {
        /** @var FormRequest $request */
        $request = $class::create("/{$recordId}", 'PATCH', $payload);
        $request->headers->set('Accept', 'application/json');
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));

        $route = new Route(['PATCH'], '/{'.$routeParameter.'}', ['uses' => fn () => null]);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        try {
            $request->validateResolved();
        } catch (ValidationException $e) {
            // The synthetic route has no URL generator; the caller only needs
            // the error bag, keyed exactly like the endpoint would key it.
            throw ValidationException::withMessages($e->errors());
        }

        return $request;
    }
}
