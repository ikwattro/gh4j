<?php

namespace Kwattro\Gh4j\EventLoader;

class IssueCommentEventLoader extends BaseEventLoader
{
	private $baseRepoId;
	private $baseRepoName;
	private $baseRepoOwner;
	private $baseRepoIsFork;
	private $baseRepoLanguage;
	private $commentUrl;
	private $issueId;
	private $commentId;

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
		$this->commentUrl = $event['url'];
		$this->issueId = $event['payload']['issue_id'];
		$this->commentId = $event['payload']['comment_id'];
		
	}

	public function buildQuery()
	{

		// Let's create our IssueComment Node and relate the Event to it
		$q = 'MERGE (comment_alias:IssueComment {id:toInt('.$this->commentId.')}) 
		MERGE (ev_alias)-[:ISSUE_COMMENT]->(comment_alias)';

		// Match/create the Issue Node
		$q .= 'MERGE (issue_alias:Issue {id:toInt('.$this->issueId.')}) ';

		// Find/Create the Repository
		$q .= 'MERGE (repo_alias:Repository {id:toInt('.$this->baseRepoId.')}) 
		MERGE (comment_alias)-[:COMMENT_ON]->(issue_alias)-[:ISSUE_ON]->(repo_alias) 
		SET repo_alias.name = \''.$this->baseRepoName.'\' 
		MERGE (owner_alias:User {name:\''.$this->baseRepoOwner.'\'}) 
		MERGE (comment_alias)-[:COMMENT_ON]->(issue_alias)-[:ISSUE_ON]->(repo_alias)-[:OWNED_BY]->(owner_alias) '; 
		
		// Let's look if the comment is bound to a PR Review
		// If yes, let's strip the part after the "#" iot have the same html_url of a PullRequest
		if (strpos($this->commentUrl, '/pull/')) {
			$expl = explode('#', $this->commentUrl);
			$htmlUrl = $expl[0];

			// Match/Create a PR Node corresponding to the htmlUrl
			// And bound the issue to the PR
			$q .= 'MERGE (pr_alias:PullRequest {html_url:\''.$htmlUrl.'\'}) ';
			$q .= 'MERGE (issue_alias)-[:BOUND_TO_PR]->(pr_alias) ';

		}

		$q = $this->getCommonEventPayloadQuery().' '.$q;

		return $q;
	}
}