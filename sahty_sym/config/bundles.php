<?php

$bundles = [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
];

$addIfInstalled = static function (string $bundleClass, array $envs) use (&$bundles): void {
    if (class_exists($bundleClass)) {
        $bundles[$bundleClass] = $envs;
    }
};

$addIfInstalled(Symfony\UX\StimulusBundle\StimulusBundle::class, ['all' => true]);
$addIfInstalled(Symfony\UX\Turbo\TurboBundle::class, ['all' => true]);
$addIfInstalled(Symfony\UX\TwigComponent\TwigComponentBundle::class, ['all' => true]);
$addIfInstalled(Twig\Extra\TwigExtraBundle\TwigExtraBundle::class, ['all' => true]);
$addIfInstalled(EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle::class, ['all' => true]);
$addIfInstalled(Symfony\Bundle\DebugBundle\DebugBundle::class, ['dev' => true]);
$addIfInstalled(Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class, ['dev' => true, 'test' => true]);
$addIfInstalled(Symfony\Bundle\MakerBundle\MakerBundle::class, ['dev' => true]);
$addIfInstalled(Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class, ['dev' => true, 'test' => true]);

return $bundles;
