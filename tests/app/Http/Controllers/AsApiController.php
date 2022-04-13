<?php

namespace Test\app\Http\Controllers;

use Illuminate\Http\Request;

trait AsApiController
{
    abstract protected function getModelClass(): string;

    abstract protected function getResourceClass(): string;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $modelClass = $this->getModelClass();

        $models = $modelClass::query()
            ->with(array_filter(explode(',', $request->input('include', ''))))
            ->paginate();

        $resourceClass = $this->getResourceClass();

        return $resourceClass::collection($models);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        throw new \Exception("no implemented");
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $modelClass = $this->getModelClass();

        $model = $modelClass
            ::with(array_filter(explode(',', $request->input('include', ''))))
            ->find($id);

        $resourceClass = $this->getResourceClass();

        return $resourceClass::make($model);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        throw new \Exception("no implemented");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        throw new \Exception("no implemented");
    }
}
