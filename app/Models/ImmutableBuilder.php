<?php

namespace Alpha\Journal\Models;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Blocks every path to an UPDATE on an immutable table, not just $model->update() — that
 * instance-level override alone doesn't catch Model::where(...)->update([...]), which Eloquent
 * executes as a raw SQL statement without hydrating models or firing events, bypassing any guard
 * placed on the model class itself. This is the one place both routes actually funnel through:
 * $model->save() and ::where(...)->update() both call Builder::update() under the hood.
 *
 * The one sanctioned exception (EditorialDecision::countersign()) opens a per-instance gate
 * before calling save(), checked here — bulk updates have no single instance to open a gate on,
 * so they're always blocked.
 */
class ImmutableBuilder extends Builder
{
    public function update(array $values): int
    {
        $model = $this->getModel();

        if (method_exists($model, 'allowsThisUpdate') && $model->allowsThisUpdate()) {
            return parent::update($values);
        }

        throw new LogicException(class_basename($model)." rows are immutable — this blocks Eloquent's query-builder update() path, not just \$model->update().");
    }

    /**
     * Same bypass risk as update(): Model::where(...)->delete() runs as raw SQL, skipping any
     * instance-level delete() override. No sanctioned exception exists for delete — unlike
     * update(), nothing ever needs to remove an activity_log or editorial_decisions row.
     */
    public function delete(): int
    {
        throw new LogicException(class_basename($this->getModel())." rows are immutable — this blocks Eloquent's query-builder delete() path, not just \$model->delete().");
    }
}
