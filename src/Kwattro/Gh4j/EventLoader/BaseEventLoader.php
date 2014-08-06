<?php

/**
 * BaseEventLoader
 *
 * Each *EventLoader is based on this class.
 *
 * This class prepares the beginning of the Cypher query that is common to all events.
 *
 * (:User)-[:DO]->(:*Event)
 *
 * User receives a name attribude
 * Event receives a timestamp attribute
 */

namespace Kwattro\Gh4j\EventLoader;

class BaseEventLoader
{


	public $actor;

	public $actorType;

	public $eventName;

	public $createdAt;

	public $repository = array();

	private $commonQuery;

	private $aliases = array();

	protected $queryAliases = array();


	public function prepareCommonEventPayloadQuery($event)
	{
		$this->resetAliases();
		$this->actor = $event['actor_attributes']['login'];
		$this->actorType = $event['actor_attributes']['type'];
		$this->createdAt = new \DateTime($event['created_at']);
		$this->eventName = $event['type'];
		$this->repository['id'] = $event['repository']['id'];
		$this->repository['name'] = $event['repository']['name'];

		$q = 'MERGE (u:User {name:\''.$this->actor.'\'})
		CREATE (ev:'.$this->eventName.' {time:toInt('.$this->createdAt->getTimestamp().') })
		MERGE (u)-[:DO]->(ev)';

		$this->commonQuery = $q;
	}

	public function getCommonEventPayloadQuery()
	{
		return $this->commonQuery;
	}

	protected function getAlias($miniAlias)
	{
		if (!array_key_exists($miniAlias.'_alias', $this->aliases))
		{
			$genAlias = 'Alias_'.hash('crc32b', microtime().$miniAlias);
			$this->aliases[$miniAlias.'_alias'] = $genAlias;
			return $genAlias;
		} else {
			return $this->aliases[$miniAlias.'_alias'];
		}
	}

	public function resetAliases()
	{
		$this->aliases = array();
	}

	public function addAliases($aliases)
	{

		foreach ($aliases as $alias) {
			$this->getAlias($alias);
		}
	}

	public function generateAliases($query)
	{
		foreach ($this->aliases as $alias_key => $value) {
			$query = str_replace($alias_key, $this->aliases[$alias_key], $query);
		}
		return $query;
	}


}