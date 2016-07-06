<?php

namespace Nova\Database\ORM;


interface ScopeInterface
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Nova\Database\ORM\Builder  $builder
     * @return void
     */
    public function apply(Builder $builder);

    /**
     * Remove the scope from the given Eloquent query builder.
     *
     * @param  \Nova\Database\ORM\Builder  $builder
     * @return void
     */
    public function remove(Builder $builder);

}
