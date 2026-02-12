<?php

namespace Moodle\Composer\Downloader;

use Composer\Composer;
use Composer\Downloader\DownloadManager;
use ReflectionNamedType;

class DownloadManagerGenerator
{
    /**
     * Constructor for DownloadManagerGenerator.
     *
     * @param Composer $composer
     * @param DownloadManager $downloadManager
     */
    public function __construct(
        private Composer $composer,
        private DownloadManager $downloadManager,
    ) {}

    /**
     * Generates a class that extends the given DownloadManager and overrides all of its methods to delegate to the original DownloadManager.
     *
     * @return DownloadManager The generated DownloadManager instance.
     */
    public function generateDownloadManager(): DownloadManager {
        $reflector = new \ReflectionClass($this->downloadManager);

        $skippedMethods = [
            '__construct',
            'install',
        ];

        $methods = [];
        foreach ($reflector->getMethods() as $method) {
            if (in_array($method->getName(), $skippedMethods, true)) {
                continue;
            }

            $methodData = (object) [
                'method_name' => $method->getName(),
                'modifier' => implode(" ", \Reflection::getModifierNames($method->getModifiers())),
            ];

            $returns = true;
            $returnsSelf = false;
            if ($method->hasReturnType()) {
                $returnType = $this->getReturnType($method->getReturnType());

                if ($returnType === "void") {
                    $returns = false;
                } else if ($returnType === "self") {
                    $returnsSelf = true;
                }

                $methodData->return_declaration = ": " . $returnType;
            }

            $declaration = "";
            if ($returns && !$returnsSelf) {
                $declaration = "return ";
            }

            $declaration = $declaration . "\$this->originalDownloader->" . $method->getName() . "(";
            $arguments = "";
            foreach ($method->getParameters() as $index => $parameter) {
                if ($index > 0) {
                    $declaration .= ", ";
                    $arguments .= ", ";
                }

                if ($parameter->hasType()) {
                    $typeValues = $this->getTypes($parameter->getType());
                    if ($typeValues !== null) {
                        $arguments .= implode("|", $typeValues) . ' ';
                    }
                }

                if ($parameter->isVariadic()) {
                    $declaration .= "...";
                    $arguments .= '...';
                }
                $declaration .= '$' . $parameter->getName();
                $arguments .= '$' . $parameter->getName();

                if ($parameter->isDefaultValueAvailable()) {
                    $arguments .= " = " . var_export($parameter->getDefaultValue(), true);
                }
            }
            $declaration .= ");\n";

            if ($returns && $returnsSelf) {
                $declaration .= "return \$this;\n";
            }

            $methodData->arguments = $arguments;
            $methodData->declaration = $declaration;

            $methods[] = $methodData;
        }


        $methodTemplate = $this->getMethodTemplate();

        $methodContent = [];
        foreach ($methods as $methodData) {
            $replacements = [
                '{modifier}' => $methodData->modifier,
                '{method_name}' => $methodData->method_name,
                '{argument_declaration}' => $methodData->arguments,
                '{return_declaration}' => $methodData->return_declaration ?? '',
                '{definition}' => rtrim($methodData->declaration),
            ];
            $methodContent[] = str_replace(
                array_keys($replacements),
                array_values($replacements),
                $methodTemplate,
            );
        }

        $namespace = "Moodle\\Composer\\Downloader";
        $classTemplate = $this->getClassTemplate();

        do {
            $className = 'MoodleDownloadManager_' . substr(md5((string) mt_rand()), 0, 8);
            $fqClassName = $namespace . '\\' . $className;
        } while (class_exists($fqClassName, false));

        $replacements = [
            '{className}' => $className,
            '{methods}' => implode("\n\n", $methodContent),
        ];

        $fullContent = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $classTemplate,
        );

        eval($fullContent);

        /** @var DownloadManager */
        return new $fqClassName($this->composer, $this->downloadManager);
    }

    private function getMethodTemplate(): string
    {
        $template = file_get_contents(__DIR__ . '/MoodleDownloadManagerMethod.tpl');
        if ($template === false) {
            throw new \RuntimeException("Failed to load method template");
        }
        return $template;
    }

    private function getClassTemplate(): string
    {
        $template = file_get_contents(__DIR__ . '/MoodleDownloadManager.php.tpl');
        if ($template === false) {
            throw new \RuntimeException("Failed to load class template");
        }
        return $template;
    }

    /**
     * Get the types from a ReflectionType, handling union types and nullable types.
     *
     * @param \ReflectionType|null $type
     * @return string[]|null
     */
    private function getTypes(?\ReflectionType $type): ?array
    {
        if ($type === null) {
            return null;
        }

        $typeValues = [];
        if ($type->allowsNull()) {
            $typeValues[] = "null";
        }

        if ($type instanceof \ReflectionUnionType) {
            $typeValues = array_merge(
                $typeValues,
                array_map(
                    fn ($subType) => $subType instanceof ReflectionNamedType && $subType->isBuiltin() ? "" . $subType : "\\" . $subType,
                    $type->getTypes(),
                ),
            );
        } else {
            $typeString = (string) $type;
            if ($type instanceof ReflectionNamedType && $type->isBuiltin()) {
                $typeValues[] = $typeString;
            } else {
                if (str_starts_with($typeString, '?')) {
                    $typeValues[] = "null";
                    $typeValues[] = "\\" . substr($typeString, 1);
                } else {
                    $typeValues[] = "\\" . $typeString;
                }
            }
        }

        return array_unique($typeValues);
    }

    /**
     * Get the return type from a \ReflectionType.
     *
     * @param \ReflectionType|null $type
     * @return string|null
     */
    private function getReturnType(?\ReflectionType $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $stringType = (string) $type;

        if ($type instanceof \ReflectionUnionType) {
            $types = [];
            foreach ($type->getTypes() as $subType) {
                $types[] = $this->getReturnType($subType);
            }

            $types = array_filter($types);
            return implode("|", $types);
        }

        if ($type instanceof ReflectionNamedType) {
            if ($type->isBuiltin()) {
                return $type->getName();
            } else if ($stringType === 'self') {
                return "self";
            } else {
                return "\\" . $type->getName();
            }
        }

        return null;
    }
}
