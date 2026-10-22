<?php

namespace Webkul\Shop\Http\Controllers\API;

use Illuminate\Http\JsonResponse;

class CoreController extends APIController
{
    /**
     * Get countries.
     *
     * @return JsonResponse
     */
    public function getCountries()
    {
        $countries = core()->countries();
        $storefrontCountries = config('app.storefront_countries', []);

        if ($storefrontCountries) {
            $countries = $countries->whereIn('code', $storefrontCountries);
        }

        return response()->json([
            'data' => $countries->map(fn ($country) => [
                'id' => $country->id,
                'code' => $country->code,
                'name' => $country->name,
            ]),
        ]);
    }

    /**
     * Get states.
     *
     * @return JsonResponse
     */
    public function getStates()
    {
        return response()->json([
            'data' => core()->groupedStatesByCountries(),
        ]);
    }
}
