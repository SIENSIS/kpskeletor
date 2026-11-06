<?php

declare(strict_types=1);

namespace Modules\Auth\Validation;

/**
 * Validation rules of the Auth settings (registered in `Config\Validation::$ruleSets` by the
 * Auth registrar).
 *
 * - `two_factor_policy`: `Auth.twoFactorPolicy` as JSON, an object whose keys are existing groups
 *   (`setting('AuthGroups.groups')`) and whose values are `required` or `optional`.
 */
final class AuthRules
{
    public function two_factor_policy(mixed $str = null, ?string &$error = null): bool
    {
        if ($str === null || (is_string($str) && trim($str) === '')) {
            return true;
        }

        $policy = is_string($str) ? json_decode($str, true) : $str;

        if (! is_array($policy) || ($policy !== [] && array_is_list($policy))) {
            $error = (string) lang('Auth.validation.twoFactorPolicy');

            return false;
        }

        $groups = setting('AuthGroups.groups');
        $groups = is_array($groups) ? $groups : config('AuthGroups')->groups;

        foreach ($policy as $group => $rule) {
            if (! is_string($group) || ! array_key_exists($group, $groups) || ! in_array($rule, ['required', 'optional'], true)) {
                $error = (string) lang('Auth.validation.twoFactorPolicy');

                return false;
            }
        }

        return true;
    }
}
