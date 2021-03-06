<?php

/**
 * This file is part of Zenify
 * Copyright (c) 2012 Tomas Votruba (http://tomasvotruba.cz)
 */

namespace Zenify\DoctrineFixtures\DI;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Faker\Generator;
use Kdyby\Console\DI\ConsoleExtension;
use Nelmio\Alice\ORM\Doctrine;
use Nette\DI\CompilerExtension;
use Nette\Utils\Validators;
use Zenify\DoctrineFixtures\Alice\AliceLoader;
use Zenify\DoctrineFixtures\Alice\Loader\Neon;
use Zenify\DoctrineFixtures\Commands\LoadFixturesCommand;
use Zenify\DoctrineFixtures\DataFixtures\Loader;


class FixturesExtension extends CompilerExtension
{

	/**
	 * @var array[]
	 */
	private $defaults = [
		'faker' => [
			'providers' => []
		],
		'alice' => [
			'seed' => 1,
			'locale' => 'cs_CZ',
			'loaders' => [
				'neon' => Neon::class
			],
		],
		'enabled' => FALSE
	];

	/**
	 * @var array
	 */
	private $fakerProviders = [];


	public function __construct()
	{
		$this->defaults['enabled'] = (PHP_SAPI === 'cli');
	}


	public function loadConfiguration()
	{
		$config = $this->getConfig($this->defaults);
		if ($config['enabled'] === FALSE) {
			return;
		}
		$this->validateConfigTypes($config);

		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('dataFixtures.purger'))
			->setClass(ORMPurger::class);

		$builder->addDefinition($this->prefix('dataFixtures.executor'))
			->setClass(ORMExecutor::class);

		$builder->addDefinition($this->prefix('dataFixtures.loader'))
			->setClass(Loader::class);

		$builder->addDefinition($this->prefix('command.loadFixtures'))
			->setClass(LoadFixturesCommand::class)
			->addTag(ConsoleExtension::COMMAND_TAG)
			->setInject(TRUE);

		$this->loadFaker($config['faker']);
		$this->loadAlice($config['alice']);
	}


	private function loadFaker(array $config)
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('faker.generator'))
			->setClass(Generator::class);

		foreach ($config['providers'] as $i => $class) {
			$builder->addDefinition($this->prefix('faker.provider.' . $i))
				->setClass($class);
			$this->fakerProviders[] = '@' . $class;
		}
	}


	private function loadAlice(array $config)
	{
		$builder = $this->getContainerBuilder();
		$builder->addDefinition($this->prefix('alice.loader'))
			->setClass(AliceLoader::class);

		$builder->addDefinition($this->prefix('alice.orm.doctrine'))
			->setClass(Doctrine::class);

		foreach ($config['loaders'] as $i => $loader) {
			$builder->addDefinition($this->prefix('alice.loader.' . $i))
				->setClass($loader)
				->setArguments([$config['locale'], $this->fakerProviders, $config['seed']])
				->addSetup('setORM', [$this->prefix('@alice.orm.doctrine')]);
		}
	}


	private function validateConfigTypes(array $config)
	{
		Validators::assertField($config, 'faker', 'array');
		Validators::assertField($config['faker'], 'providers', 'list');
		Validators::assertField($config, 'alice', 'array');
		Validators::assertField($config['alice'], 'seed', 'int');
		Validators::assertField($config['alice'], 'locale', 'string');
		Validators::assertField($config['alice'], 'loaders', 'array');
	}

}
