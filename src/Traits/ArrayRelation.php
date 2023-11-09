<?php

namespace App\Traits;

trait ArrayRelation
{
    protected $unsaved_relations = [];

    public static function bootArrayRelation()
    {
        parent::boot();

        self::created(function ($model) {
            if (!empty($model->unsaved_relations)) {
                foreach ($model->unsaved_relations as $record) {
                    $record->save();
                }
            }
        });
    }

    protected function setRelationFromArray(string $relation, array $items, bool $remove_existing = true)
    {
        $remove_existing &= $this->exists;

        $remove = $remove_existing ? $this->$relation()->pluck('id', 'id')->all() : [];
        $relation_class = get_class($this->$relation()->getRelated());

        foreach ($items as $key => $item) {
            $item = array_map(function ($value) {

                return $value !== '' ? $value : null;
            }, $item);

            $item[$this->$relation()->getForeignKeyName()] = $this->exists ? $this->id : get_ai($this->getTable());

            if (!array_key_exists('sort_order', $item)) {
                $item['sort_order'] = $key;
            }

            $related = new $relation_class;

            if (!empty($item['id'])) {
                unset($remove[$item['id']]);

                $related = $this->$relation()->where('id', $item['id'])->first();
            }

            $related->fill($item);

            if ($this->exists) {

                $related->save();
            } else {
                $this->unsaved_relations[] = $related;
            }
        }

        if ($remove_existing) {

            $this->$relation()->whereIn('id', $remove)->delete();
        }

        $this->unsetRelation($relation);
    }

    protected function getArrayRelations(): array
    {
        return is_array($this->arrayRelations) ? $this->arrayRelations : [];
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->getArrayRelations()) && is_array($value)) {
            $this->setRelationFromArray($key, $value);
        } else {
            parent::setAttribute($key, $value);
        }
    }

    public function fill(array $attributes)
    {
        foreach ($this->getArrayRelations() as $relation) {
            if (isset($attributes[$relation]) && is_array($attributes[$relation])) {
                $this->setRelationFromArray($relation, $attributes[$relation]);

                unset($attributes[$relation]);
            }
        }

        return parent::fill($attributes);
    }
}
