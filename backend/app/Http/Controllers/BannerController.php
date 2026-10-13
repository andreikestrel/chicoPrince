<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBannerRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Models\CatalogCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BannerController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BannerResource::collection(
            Banner::where('active', true)
                ->whereIn('category', CatalogCategory::all()->pluck('name'))
                ->orderBy('position')->orderBy('id')->limit(20)->get()
        );
    }

    public function manage(): AnonymousResourceCollection
    {
        return BannerResource::collection(Banner::orderBy('position')->orderBy('id')->paginate(20));
    }

    public function store(SaveBannerRequest $request): BannerResource
    {
        return new BannerResource(Banner::create($request->validated()));
    }

    public function update(SaveBannerRequest $request, Banner $banner): BannerResource
    {
        $banner->update($request->validated());

        return new BannerResource($banner);
    }

    public function destroy(Banner $banner): Response
    {
        $banner->delete();

        return response()->noContent();
    }
}
