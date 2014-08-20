<?php

namespace Kwattro\Gh4j\EventLoader;

class ForkEventLoader extends BaseEventLoader
{
	private $baseRepoId;
	private $baseRepoName;
	private $baseRepoOwner;
	private $baseRepoIsFork;
	private $baseRepoLanguage;
	private $forkRepoUrl;



	public function getEventLoadQuery(array $event)
	{
		$this->prepareCommonEventPayloadQuery($event);
		$this->prepareEventTypePayloadQuery($event);
		
		return $this->buildQuery();
	}

	public function prepareEventTypePayloadQuery($event)
	{
		$repository = $event['repository'];
		$this->baseRepoId = $repository['id'];
		$this->baseRepoName = $repository['name'];
		$this->baseRepoOwner = $repository['owner'];
		$this->baseRepoIsFork = $repository['fork'];
		$this->forkRepoUrl = $event['url'];
		//var_dump($this->forkRepoUrl);
		
	}

	public function buildQuery()
	{
		// We create the new Fork, which is also a repository in itself, then we relate the FORK event to the Fork
		$q = 'MERGE (fork_alias:Fork:Repository {name:\''.$this->baseRepoName.'\', owned_by:\''.$this->actor.'\'})
		ON CREATE SET fork_alias.owned_by = u.name
		SET fork_alias.html_url =\''.$this->forkRepoUrl.'\' 
		WITH ev, u, fork_alias
		MERGE (ev)-[:FORK]->(fork_alias)-[:OWNED_BY]->(u)';

		// The fork is based on a Repository that is owned by a User
		$q .= ' MERGE (bro_alias:User {name:\''.$this->baseRepoOwner.'\'}) 
		MERGE (br_alias:Repository {id:toInt('.$this->baseRepoId.'), name:\''.$this->baseRepoName.'\'})
		MERGE (br_alias)-[:OWNED_BY]->(bro_alias) 
		MERGE (fork_alias)-[:FORK_OF]->(br_alias)';

		// If the repository the created Fork is based on is also a Fork, we add the Fork label to it
		if ($this->baseRepoIsFork == true) {
			$q .= ' SET br_alias :Fork';
		}

		$this->addAliases(array('fork', 'bro', 'br'));

		return $this->getCommonEventPayloadQuery().' '.$q;
	}
}