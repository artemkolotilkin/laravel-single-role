<?php

declare(strict_types = 1);

namespace McMatters\SingleRole\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use McMatters\SingleRole\Models\Permission;
use McMatters\SingleRole\Models\Role;

/**
 * Class HasPermission
 *
 * @package McMatters\SingleRole\Traits
 */
trait HasPermission
{
    /**
     * @var array
     */
    protected static $cachedPermissions = [];

    /**
     * @return BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            null,
            null,
            'permission_id',
            'permissions'
        );
    }

    /**
     * @param string $permission
     *
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getPermissions();

        return (bool) $permissions->first(function ($item) use ($permission) {
            return is_numeric($permission)
                ? $item->getKey() === (int) $permission
                : $item->getAttribute('name') === $permission;
        });
    }

    /**
     * @param $permissions
     * @param bool $all
     *
     * @return bool
     */
    public function hasPermissions($permissions, $all = false): bool
    {
        if (is_string($permissions)) {
            $permissions = explode('|', $permissions);
        }

        foreach ($permissions as $permission) {
            $hasPermission = $this->hasPermission($permission);

            if ($hasPermission && !$all) {
                return true;
            }

            if (!$hasPermission && $all) {
                return false;
            }
        }

        return $all;
    }

    /**
     * @return Collection
     */
    public function getPermissions(): Collection
    {
        $class = get_class($this);
        $key = $this->getKey();

        if (isset(self::$cachedPermissions[$class][$key])) {
            return self::$cachedPermissions[$class][$key];
        }

        if ($this instanceof Role) {
            $modelPermissions = $this->getAttribute('permissions');
        } else {
            /** @var null|Role $role */
            $role = $this->getAttribute('role');
            $modelPermissions = $this->getAttribute('permissions');

            if (null !== $role) {
                $modelPermissions = $modelPermissions->merge(
                    $role->getAttribute('permissions')
                );
            }
        }

        self::$cachedPermissions[$class][$key] = $modelPermissions;

        return $modelPermissions;
    }

    /**
     * @param mixed $id
     * @param array $attributes
     * @param bool $touch
     *
     * @return $this
     */
    public function attachPermissions(
        $id,
        array $attributes = [],
        bool $touch = true
    ) {
        $this->permissions()->attach($id, $attributes, $touch);
        $this->updateCachedPermissions();

        return $this;
    }

    /**
     * @param null $ids
     * @param bool $touch
     *
     * @return $this
     */
    public function detachPermissions($ids = null, bool $touch = true)
    {
        $this->permissions()->detach($ids, $touch);
        $this->updateCachedPermissions();

        return $this;
    }

    /**
     * @param Collection|array $ids
     * @param bool $detaching
     *
     * @return $this
     */
    public function syncPermissions($ids, bool $detaching = true)
    {
        $this->permissions()->sync($ids, $detaching);
        $this->updateCachedPermissions();

        return $this;
    }

    /**
     * @return void
     */
    protected function updateCachedPermissions()
    {
        self::$cachedPermissions[get_class($this)][$this->getKey()] = $this
            ->permissions()
            ->get();
    }
}
