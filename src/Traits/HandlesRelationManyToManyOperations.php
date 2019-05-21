<?php

namespace Laralord\Orion\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait HandlesRelationManyToManyOperations
{

    /**
     * Sync relation resources.
     *
     * @param Request $request
     * @param int $resourceID
     * @return JsonResponse
     */
    public function sync(Request $request, $resourceID)
    {
        $beforeHookResult = $this->beforeSync($request, $resourceID);
        if ($this->hookResponds($beforeHookResult)) {
            return $beforeHookResult;
        }

        $resourceEntity = $this->buildMethodQuery($request)->with($this->relationsFromIncludes($request))->findOrFail($resourceID);
        if ($this->authorizationRequired()) {
            $this->authorize('update', $resourceEntity);
        }

        $syncResult = $resourceEntity->{static::$relation}()->sync($this->prepareResourcePivotFields($request->get('resources')), $request->get('detaching', true));

        $afterHookResult = $this->afterSync($request, $syncResult);
        if ($this->hookResponds($afterHookResult)) {
            return $afterHookResult;
        }

        return response()->json($syncResult);
    }

    /**
     * Toggle relation resources.
     *
     * @param Request $request
     * @param int $resourceID
     * @return JsonResponse
     */
    public function toggle(Request $request, $resourceID)
    {
        $beforeHookResult = $this->beforeToggle($request, $resourceID);
        if ($this->hookResponds($beforeHookResult)) {
            return $beforeHookResult;
        }

        $resourceEntity = $this->buildMethodQuery($request)->with($this->relationsFromIncludes($request))->findOrFail($resourceID);
        if ($this->authorizationRequired()) {
            $this->authorize('update', $resourceEntity);
        }

        $togleResult = $resourceEntity->{static::$relation}()->toggle($this->prepareResourcePivotFields($request->get('resources')));

        $afterHookResult = $this->afterToggle($request, $togleResult);
        if ($this->hookResponds($afterHookResult)) {
            return $afterHookResult;
        }

        return response()->json($togleResult);
    }

    /**
     * Attach resource to the relation.
     *
     * @param Request $request
     * @param int $resourceID
     * @return JsonResponse
     */
    public function attach(Request $request, $resourceID)
    {
        $beforeHookResult = $this->beforeAttach($request, $resourceID);
        if ($this->hookResponds($beforeHookResult)) {
            return $beforeHookResult;
        }

        $resourceEntity = $this->buildMethodQuery($request)->with($this->relationsFromIncludes($request))->findOrFail($resourceID);
        if ($this->authorizationRequired()) {
            $this->authorize('update', $resourceEntity);
        }

        if ($request->get('duplicates')) {
            $attachResult = $resourceEntity->{static::$relation}()->attach($this->prepareResourcePivotFields($request->get('resources')));
        } else {
            $attachResult = $resourceEntity->{static::$relation}()->sync($this->prepareResourcePivotFields($request->get('resources')), false);
        }

        $afterHookResult = $this->afterAttach($request, $attachResult);
        if ($this->hookResponds($afterHookResult)) {
            return $afterHookResult;
        }

        return response()->json([
            'attached' => array_get($attachResult, 'attached', [])
        ]);
    }

    /**
     * Detach resource to the relation.
     *
     * @param Request $request
     * @param int $resourceID
     * @return JsonResponse
     */
    public function detach(Request $request, $resourceID)
    {
        $beforeHookResult = $this->beforeDetach($request, $resourceID);
        if ($this->hookResponds($beforeHookResult)) {
            return $beforeHookResult;
        }

        $resourceEntity = $this->buildMethodQuery($request)->with($this->relationsFromIncludes($request))->findOrFail($resourceID);
        if ($this->authorizationRequired()) {
            $this->authorize('update', $resourceEntity);
        }

        $detachResult = $resourceEntity->{static::$relation}()->detach($this->prepareResourcePivotFields($request->get('resources')));

        $afterHookResult = $this->afterDetach($request, $detachResult);
        if ($this->hookResponds($afterHookResult)) {
            return $afterHookResult;
        }

        return response()->json([
            'detached' => array_values($request->get('resources', []))
        ]);
    }

    /**
     * Update relation resource pivot.
     *
     * @param Request $request
     * @param int $resourceID
     * @param int $relationID
     * @return JsonResponse
     */
    public function updatePivot(Request $request, $resourceID, $relationID)
    {
        $beforeHookResult = $this->beforeUpdatePivot($request, $relationID);
        if ($this->hookResponds($beforeHookResult)) {
            return $beforeHookResult;
        }

        $resourceEntity = $this->buildMethodQuery($request)->with($this->relationsFromIncludes($request))->findOrFail($resourceID);
        if ($this->authorizationRequired()) {
            $this->authorize('update', $resourceEntity);
        }

        $updateResult = $resourceEntity->{static::$relation}()->updateExistingPivot($relationID, $this->preparePivotFields($request->get('pivot', [])));

        $afterHookResult = $this->afterUpdatePivot($request, $updateResult);
        if ($this->hookResponds($afterHookResult)) {
            return $afterHookResult;
        }

        return response()->json([
            'updated' => [is_numeric($relationID) ? (int) $relationID : $relationID]
        ]);
    }

    /**
     * Retrieves only fillable pivot fields and json encodes any objects/arrays.
     *
     * @param array $resources
     * @return array
     */
    protected function prepareResourcePivotFields($resources)
    {
        $resources = array_wrap($resources);

        foreach ($resources as $key => &$pivotFields) {
            if (!is_array($pivotFields)) {
                continue;
            }
            $pivotFields = array_only($pivotFields, $this->pivotFillable);
            $pivotFields = $this->preparePivotFields($pivotFields);
        }

        return $resources;
    }

    /**
     * Json encodes any objects/arrays of the given pivot fields.
     *
     * @param array $pivotFields
     * @return array mixed
     */
    protected function preparePivotFields($pivotFields)
    {
        foreach ($pivotFields as &$field) {
            if (is_array($field) || is_object($field)) {
                $field = json_encode($field);
            }
        }

        return $pivotFields;
    }

    /**
     * Casts pivot json fields to array on the given entity.
     *
     * @param Model $entity
     * @return Model
     */
    protected function castPivotJsonFields($entity)
    {
        if (!$entity->pivot) {
            return $entity;
        }

        foreach ($this->pivotJson as $pivotJsonField) {
            if (!$entity->pivot->{$pivotJsonField}) {
                continue;
            }
            $entity->pivot->{$pivotJsonField} = json_decode($entity->pivot->{$pivotJsonField}, true);
        }
        return $entity;
    }


    /**
     * The hook is executed before syncing relation resources.
     *
     * @param Request $request
     * @param int $id
     * @return mixed
     */
    protected function beforeSync(Request $request, $id)
    {
        return null;
    }

    /**
     * The hook is executed after syncing relation resources.
     *
     * @param Request $request
     * @param array $syncResult
     * @return mixed
     */
    protected function afterSync(Request $request, &$syncResult)
    {
        return null;
    }

    /**
     * The hook is executed before toggling relation resources.
     *
     * @param Request $request
     * @param int $id
     * @return mixed
     */
    protected function beforeToggle(Request $request, $id)
    {
        return null;
    }

    /**
     * The hook is executed after toggling relation resources.
     *
     * @param Request $request
     * @param array $toggleResult
     * @return mixed
     */
    protected function afterToggle(Request $request, &$toggleResult)
    {
        return null;
    }

    /**
     * The hook is executed before attaching relation resource.
     *
     * @param Request $request
     * @param int $id
     * @return mixed
     */
    protected function beforeAttach(Request $request, $id)
    {
        return null;
    }

    /**
     * The hook is executed after attaching relation resource.
     *
     * @param Request $request
     * @param array $toggleResult
     * @return mixed
     */
    protected function afterAttach(Request $request, &$toggleResult)
    {
        return null;
    }

    /**
     * The hook is executed before detaching relation resource.
     *
     * @param Request $request
     * @param int $id
     * @return mixed
     */
    protected function beforeDetach(Request $request, $id)
    {
        return null;
    }

    /**
     * The hook is executed after detaching relation resource.
     *
     * @param Request $request
     * @param array $toggleResult
     * @return mixed
     */
    protected function afterDetach(Request $request, &$toggleResult)
    {
        return null;
    }

    /**
     * The hook is executed before updating relation resource pivot.
     *
     * @param Request $request
     * @param int $id
     * @return mixed
     */
    protected function beforeUpdatePivot(Request $request, $id)
    {
        return null;
    }

    /**
     * The hook is executed after updating relation resource pivot.
     *
     * @param Request $request
     * @param array $updateResult
     * @return mixed
     */
    protected function afterUpdatePivot(Request $request, &$updateResult)
    {
        return null;
    }
}