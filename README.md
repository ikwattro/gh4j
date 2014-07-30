# [WIP] Gh4j - PHP

### Import easily Github Events Data Archive into a Neo4j Graph Database

#### Disclaimer !

This is not compatible with the Github ReST API as the Event payloads are totally different, btw this can be used as a sandbox for a further switch to the API.


### What ?

This is a simple library that will parse Github Events Archive files and load these Events in a Neo4j Graph DB.

This consists of a simple entry point for loading the data and some `EventType` Loaders that will generate the needed Cypher Query for inserting the data. This lib is coupled with a dead simple connection library to access the Neo4j ReST API.

It will also create relationships between Events, Repositories, Users, Forks, Comments so that you can have a full overview of what is going on Github and how it is related.

#### `Attention/Achtung/Ola:`

This lib is made primarely as a personal experiment, the DB Schema that the queries will produce are proper to the manner I intend to manipulate the data, I do not pretend it is the best schema to use but it is a good exercise for playing with the Neo4j graph DB.

Of course, suggestions, point of views, PR's, ... are always welcome.

NB: FYI there are approximately 8000 events in an hour on Github

### How to use

#### 1. Require the `Gh4j` library in your project dependencies

Add the following requirement to your `composer.json` file :

```json
"require":{
	// ..... other dependencies
	"kwattro/gh4j" : "*"
}
```

### 2. Instantiate the `Gh4j` class

```php
require 'vendor/autoload.php';

use Kwattro\Gh4j\Gh4j;

$gh = new Gh4j();
```

### 3. Download Github Events Data Archive

You can download the data archives on the [Github Archive Website](http://www.githubarchive.org) .
Just follow the instructions and download the data for the period you want.

Unzip the download file somewhere on your computer.

### 4. Load the data in the database

The download file is not a valid JSON, but contains lines that are valid JSON representing `Event` occurences.

With the php `file` function, you'll get the file in an array_format with each rows containing JSON Event Objects, loop through the array and load the events :

```php
$events = file('/Users/kwattro/gh/data/2014-06-01-3.json', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($events as $event) {
	$gh->loadEvent($event);
}
```

The `Gh4j` library will then check validity of the JSON, load the appropriate `EventType` Loader and insert the data in the DB.

By default, each `loadEvent()` call will trigger a connection to the DB for inserting the data. You can use a `Stack` method that will empile * number of queries and flush it when the specified limit is reached.

To use it, you need to instruct the loadMethod to use stackMethod to do so by supplying `true` as the second argument

```php

// Code to read your file ....
foreach ($events as $event) {
	$gh->loadEvent($event, true);
}
// Don't forget to flush after the loop to insert remaining queued queries
$gh->flush();
```

The above will empile queries until a limit is reached and send the stack in one big query. With a limit of 50, this is going 2x faster than calling each time the DB for a file containing about 8000 events and is inserted on a small setup in 50 seconds (1 hour file);

You can adjust the limit of the `stack` by accessing the DB connector :

```php
$gh = new Gh4j():
$conn = $gh->getConnector();
$conn->setStackFlushLimit(30); // Stack will be flushed after 30 queries
```

---

### Event Types supported

Currently there is 4 EventTypes handled :

* PushEvent
* PullRequestEvent
* ForkEvent
* IssueCommentEvent

Each type will be handled by a custom EventLoader, which extend a BaseEventLoader that creates Cypher query for the common payload of the Event.

I suggest that you look at the code comments inside the EventLoader directory to have an overview of how the data will be inserted, or at the end of this README to the section `Generated Cypher Queries`.

If a not supported EventType is encountered in the data, he will be skipped.

More will come ...


---

### Generated Cypher Queries examples

#### PushEvent

```
MERGE (u:User {name:'ZhukV'}) CREATE (ev:PushEvent {time:toInt(1401606330) }) MERGE (u)-[:DO]->(ev) 
MERGE (repo:Repository {id:toInt(20051270)}) 
SET repo.name='Unicode' 
MERGE (branch:Branch {ref:'refs/heads/master', repo_id:toInt(20051270)}) 
MERGE (ev)-[:PUSH_TO]->(branch) 
MERGE (branch)-[:BRANCH_OF]->(repo) 
MERGE (owner:User {name:'ZhukV'}) 
MERGE (repo)-[:OWNED_BY]->(owner) 
```

Expl: 

-> Match or Create the User doing the Event 
-> Relates the User to this Event 
-> Match or Create the Repository it is pushed to 
-> Match or Create the Branch it is pushed to
-> Relates Event is PUSH_TO the Branch
-> Branch is BRANCH_OF Repository
-> Match or Create Owner of the Repository
-> Repository that is OWNED_BY somebody

### PullRequestEvent

```
MERGE (u:User {name:'pixelfreak2005'}) 
CREATE (ev:PullRequestEvent {time:toInt(1401606356) }) 
MERGE (u)-[:DO]->(ev) 
MERGE (pr:PullRequest {html_url:'https://github.com/pixelfreak2005/liqiud_android_packages_apps_Settings/pull/2'}) 
SET pr += { id:toInt(16573622), number:toInt(2), state:'open'} 
MERGE (ev)-[:PR_OPEN]->(pr)
MERGE (ow:User {name:'pixelfreak2005'}) 
MERGE (or:Repository {id:toInt(20338536), name:'liqiud_android_packages_apps_Settings'}) 
MERGE (or)-[:OWNED_BY]->(ow) 
MERGE (pr)-[:PR_ON_REPO]->(or)
```


### ForkEvent

```
MERGE (u:User {name:'rudymalhi'}) 
CREATE (ev:ForkEvent {time:toInt(1401606379) }) MERGE (u)-[:DO]->(ev) 
CREATE (fork:Fork:Repository {name:'Full-Stack-JS-Nodember'}) 
MERGE (ev)-[:FORK]->(fork)-[:OWNED_BY]->(u) 
MERGE (bro:User {name:'mgenev'}) 
MERGE (br:Repository {id:toInt(15503488), name:'Full-Stack-JS-Nodember'})-[:OWNED_BY]->(bro) 
MERGE (fork)-[:FORK_OF]->(br)
```
### IssueCommentEvent

```
MERGE (u:User {name:'johanneswilm'}) 
CREATE (ev:IssueCommentEvent {time:toInt(1401606384) }) 
MERGE (u)-[:DO]->(ev) 
MERGE (comment:IssueComment {id:toInt(44769338)}) 
MERGE (ev)-[:ISSUE_COMMENT]->(comment)
MERGE (issue:Issue {id:toInt(34722578)}) 
MERGE (repo:Repository {id:toInt(14487686)}) 
MERGE (comment)-[:COMMENT_ON]->(issue)-[:ISSUE_ON]->(repo) 
SET repo.name = 'diffDOM' 
MERGE (owner:User {name:'fiduswriter'}) 
MERGE (comment)-[:COMMENT_ON]->(issue)-[:ISSUE_ON]->(repo)-[:OWNED_BY]->(owner)
```

I listen to all suggestions that can improve query performances :)




