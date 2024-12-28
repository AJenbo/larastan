<?php

declare(strict_types=1);

namespace Larastan\Larastan\ReturnTypes;

use Larastan\Larastan\Concerns\HasContainer;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ErrorType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Throwable;

use function class_exists;
use function count;
use function interface_exists;

final class AppMakeHelper
{
    use HasContainer;

    public function resolveTypeFromCall(FuncCall|MethodCall|StaticCall $call, Scope $scope): Type|null
    {
        $args = $call->getArgs();
        if (count($args) === 0) {
            return new ErrorType();
        }

        $argType = $scope->getType($args[0]->value);

        $constantStrings = $argType->getConstantStrings();

        if (count($constantStrings) > 0) {
            $types = [];
            foreach ($constantStrings as $constantString) {
                try {
                    $class = $constantString->getValue();
                    /** @var object|null $resolved */
                    $resolved = $this->resolve($class);

                    if ($resolved !== null) {
                        $class = $resolved::class;
                    } elseif (! class_exists($class) && ! interface_exists($class)) {
                        return new ErrorType();
                    }

                    $types[] = new ObjectType($class);
                } catch (Throwable) {
                    return new ErrorType();
                }
            }

            return TypeCombinator::union(...$types);
        }

        return null;
    }
}
