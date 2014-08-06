<?php

namespace Kwattro\Gh4j;

use Kwattro\Gh4j\Loader;
use Kwattro\Gh4j\EventLoader\BaseEventLoader;
use Kwattro\Gh4j\Validator\JSONValidator;
use Kwark\Kwark;

class Gh4j
{

	private $connector;
	private $loader;
	private $validator;

	public function __construct()
	{
		$this->connector = new Kwark();
        $this->connector->initalizeApi();

		$this->loader = new Loader();
		$this->validator = new JSONValidator();
	}

	public function getConnector()
	{
		return $this->connector;
	}

	public function loadEvent($event, $inStack = false)
	{
		if ($ev = $this->validateJSON($event)) {

			$loader = $this->loader->getEventLoader($ev);

			if ($loader instanceof BaseEventLoader) {
				$query = $loader->getEventLoadQuery($ev);
				if ($inStack) {
				return $this->connector->addToParallelStack($query);
			}

			return $this->connector->sendCypherQuery($query);

			}
		}
	}

	public function flushStack()
	{
		return $this->connector->flushParallelStack();
	}

	public function flush()
	{
		return $this->connector->flush();
	}


	public function validateJSON($string)
	{
		return $this->validator->validate($string, true);
	}

	public function initalizeDBCI()
	{
		$constraints = array(
			'u:User' => 'u.name',
			'r:Repository' => 'r.id',
			'pr:PullRequest' => 'pr.html_url'
		);

		$indexes = array(
			'Event' => 'time',
			'Branch' => 'repo_id',
			'Fork' => 'name',
			);
		
		foreach ($constraints as $key => $value) {
			$d = 'DROP CONSTRAINT ON ('.$key.') ASSERT '.$value.' IS UNIQUE;';
			$q = 'CREATE CONSTRAINT ON ('.$key.') ASSERT '.$value.' IS UNIQUE;';
			$this->sendMultiple(array($d, $q));
		}

		foreach ($indexes as $k => $v) {
			$d = 'DROP INDEX ON :'.$k.'('.$v.');';
			$q = 'CREATE INDEX ON :'.$k.'('.$v.');';
			$this->sendMultiple(array($q));
		}
	}

	public function sendMultiple(array $queries)
	{
		foreach ($queries as $q) {
			try {
				$this->connector->sendCypherQuery($q);
			} catch (\GuzzleHttp\Exception\TransferException $e) {
				return;
			}
		}
	}
}