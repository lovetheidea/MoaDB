<?php

/**
 * The MIT License (MIT)
 * Copyright (c) 2013-2016 MoaDB
 * Please set "short_open_tag = On" in your php.ini file 
 *
 * @version 1.0.2
 * @author Jay - Love the Idea
 * @license GPL v3
 */

ini_set('mongo.long_as_object', 1);
require_once 'resources/php/load.php';

if($showUserPassword) : ?>
<div class="col-md-offset-4">
	<form role="form" class="col-md-5">
		<h3 class="text-muted">moa[db]</h3>
		<?= isset($_POST['errors']) ? '<div class="alert alert-warning>'.$_POST['errors']['username'].'</div>' : '' ?>
		<div class="form-group">
			<label for="exampleInputEmail1">Username</label>
			<input type="text" class="form-control" id="exampleInputEmail1" name="username" placeholder="Enter Username">
		</div>
		<div class="form-group">
			<label for="exampleInputPassword1">Password</label>
			<input type="password" class="form-control" id="exampleInputPassword1" name="password" placeholder="Password">
		</div>
		<button type="submit" class="btn btn-default">Submit</button>
	</form>
	</div>
<?php exit; endif; ?>

<div class="container">
	<div class="header">
        <ul class="nav nav-pills pull-right">
			<?php if ($hasCollection) : ?>
				<li class="active"><a data-view="CollectionRow" href="<?= $baseUrl ?>?db=<?= $db ?>&action=listRows&collection=<?= $collection ?>">&nbsp;<icon class="icon-list">&nbsp;</icon></a></li>
			<?php endif; ?>
			<?php if ($hasDB) : ?>
				<li <?= $hasCollection ? '' : 'class="active"' ?>><a data-view="Collections" href="<?= $baseUrl ?>?db=<?= $db ?>"><icon class="icon-inbox"></icon> Collections</a></li>
			<?php endif; ?>
			<li <?= $hasDB ? '' : 'class="active"' ?>><a data-view="Databases" href="<?= $baseUrl ?>"><icon class="icon-hdd"></icon> Databases</a></li>
			<li><a class="divider">|</a></li>
			<li><a id="edit-button"><icon class="icon-pencil"></icon> Edit</a></li>
			<li><a data-popup data-title="<?= $hasDB ? ($hasCollection ? 'New Object' : 'New Collection') : 'New Database"' ?>" data-body="<?= $hasDB ? ($hasCollection ? '#new-object' : '#new-collection') : '#new-database"' ?>"><icon class="icon-plus-sign-alt"></icon> <?= $hasCollection ? 'Insert' : 'Add' ?></a></li>
        </ul>
        <h3 class="text-muted">moa[db]</h3>
	</div>

	<div class="jumbotron">
		<div class="row marketing <?= $hasDB ? 'noMargin' : '' ?>">
			<?php
			if (!$hasDB) :
				$newRow = 0;
				foreach ($mo->mongo['dbs'] as $db => $desc):
					?>
					<div class="col-lg-3 database click">
						<?= $html->link("javascript: dropDatabase('" . get::htmlentities($db) . "'); void(0);", '&times;', ['class' => 'close hidden', 'title' => 'Drop Database']) ?>
						<img src="./resources/images/database.png" class="wiggle" />
						<h4><?= $db ?></h4>
						<p><?= $desc ?></p>
					</div>
					<?php
				endforeach;
			elseif ($hasDB) :
				if (isset($mo->mongo['listCollections'])) :
					?>
					<div id="mongo_collections" class="side-nav">
						<?php
						if (!$mo->mongo['listCollections']) :
							echo $html->div('No collections exist');
						else :
							?>
							<table class="table collection click">
								<tbody>
									<?php
									$totalcount = 0;
									foreach ($mo->mongo['listCollections'] as $col => $rowCount) :
										$totalcount += $rowCount;
										?>
										<tr>
											<td>
									<icon class="shown icon-inbox"></icon>
									<?= $html->link("javascript: collectionDrop('" . urlencode($col) . "'); void(0);", '&times;', ['class' => 'close hidden', 'title' => 'Drop Collection']) ?>
									</td>
									<td><?= $html->link($baseUrl . '?db=' . $dbUrl . '&action=listRows&collection=' . urlencode($col), $col, ['class' => '']) ?></td>
									<td><small title="<?= number_format($rowCount) ?>"><?= '(' . $html->bd_nice_number($rowCount) . ')' ?></small></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						<?
						endif;
						$url = $baseUrl . '?' . http_build_query($_GET);
						if (isset($collection)) {
							$url .= '&collection=' . urlencode($collection);
						}

//					SET limits #TODO
//					-----
//					echo $form->open(array('action' => $url, 'style' => 'width: 80px; height: 20px;'))
//					. $form->input(array('name' => 'limit', 'value' => $_SESSION['limit'], 'label' => '', 'addBreak' => false,
//						'style' => 'width: 40px;'))
//					. $form->submit(array('value' => 'limit', 'class' => 'ui-state-hover'))
//					. $form->close();
//
						?>
					</div>
					<?
				endif;
			endif;
			?>
			<div id="main-content">
				<?php
				//stats on main page
				if ($hasDB && isset($mo->mongo['getStats'])) :
					?>
					<div class="content-scroll full">
						<div class="alert alert-success stats">You have <?= number_format((isset($totalcount) ? $totalcount : 0)) ?> records and <?= count($mo->mongo['listCollections']) ?> collections.</div>
						<div class="alert alert-info stats <?= (isset($totalcount) ? 'opacity' : '') ?>">To add a new collection click 'Add' in the top right-hand corner.</div>
						<table class="table stats">
							<tbody>
								<tr><td><h4>Database : <?= $db ?></h4></td></tr>
								<?php foreach ($mo->mongo['getStats'] as $key => $val) : ?>
									<tr>
										<td>
											<?php
											if (!is_array($val)) {
												echo $key . ': ' . $val;
											} else {
												echo $key . '<ul>';
												foreach ($val as $subkey => $subval) {
													echo $html->li((is_int($subkey) ? '' : $subkey . ': ') . (is_array($subval) ? print_r($subval) : $subval));
												}
												echo '</ul>';
											}
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php
				endif;
				unset($mo->mongo['getStats']);

				//show collection object list
				if (isset($mo->mongo['listRows'])) {

//				Title and renaming #TODO
//				-----
//				echo $form->open(array('action' => $baseUrl . '?db=' . $dbUrl . '&action=renameCollection',
//					'style' => 'width: 600px; display: none;', 'id' => 'renamecollectionform'))
//				. $form->hidden(array('name' => 'collectionfrom', 'value' => $collection))
//				. $form->input(array('name' => 'collectionto', 'value' => $collection, 'label' => '', 'addBreak' => false))
//				. $form->submit(array('value' => 'Rename Collection', 'class' => 'ui-state-hover'))
//				. $form->close();
//				$js = "$('#collectionname').hide(); $('#renamecollectionform').show(); void(0);";
//				echo $collection.'<h1 id="collectionname">' . $html->link('javascript: ' . $js, $collection) . '</h1>';
//				Create and delete Mongo Indexes
//				------
//				if (isset($mo->mongo['listIndexes'])) {
//					echo '<ol id="indexes" style="display: none; margin-bottom: 10px;">';
//					echo $form->open(array('method' => 'get'));
//					echo '<div id="indexInput">'
//					. $form->input(array('name' => 'index[]', 'label' => '', 'addBreak' => false))
//					. $form->checkboxes(array('name' => 'isdescending[]', 'options' => array('Descending')))
//					. '</div>'
//					. '<a id="addindexcolumn" style="margin-left: 160px;" href="javascript: '
//					. "$('#addindexcolumn').before('<div>' + $('#indexInput').html().replace(/isdescending_Descending/g, "
//					. "'isdescending_Descending' + mo.indexCount++) + '</div>'); void(0);"
//					. '">[Add another index field]</a>'
//					. $form->radios(array('name' => 'unique', 'options' => array('Index', 'Unique'), 'value' => 'Index'))
//					. $form->submit(array('value' => 'Add new index', 'class' => 'ui-state-hover'))
//					. $form->hidden(array('name' => 'action', 'value' => 'ensureIndex'))
//					. $form->hidden(array('name' => 'db', 'value' => get::htmlentities($db)))
//					. $form->hidden(array('name' => 'collection', 'value' => $collection))
//					. $form->close();
//					foreach ($mo->mongo['listIndexes'] as $indexArray) {
//						$index = '';
//						foreach ($indexArray['key'] as $key => $direction) {
//							$index .= (!$index ? $key : ', ' . $key);
//							if (!is_object($direction)) {
//								$index .= ' [' . ($direction == -1 ? 'desc' : 'asc') . ']';
//							}
//						}
//						if (isset($indexArray['unique']) && $indexArray['unique']) {
//							$index .= ' [unique]';
//						}
//						if (key($indexArray['key']) != '_id' || count($indexArray['key']) !== 1) {
//							$index = '[' . $html->link($baseUrl . '?db=' . $dbUrl . '&collection=' . urlencode($collection)
//											. '&action=deleteIndex&index='
//											. serialize($indexArray['key']), 'X', array('title' => 'Drop Index',
//										'onclick' => "mo.confirm.href=this.href; "
//										. "mo.confirm('Are you sure that you want to drop this index?', "
//										. "function() {window.location.replace(mo.confirm.href);}); return false;")
//									) . '] '
//									. $index;
//						}
//						echo '<li>' . $index . '</li>';
//					}
//					echo '</ol>';
//				}

					$objCount = $mo->mongo['listRows']->count(true); //count of rows returned

					$paginator = number_format($mo->mongo['count']) . ' objects'; //count of rows in collection
					if ($objCount && $mo->mongo['count'] != $objCount) {
						$skip = (isset($_GET['skip']) ? $_GET['skip'] : 0);
						$get = $_GET;
						unset($get['skip']);
						$url = $baseUrl . '?' . http_build_query($get) . '&collection=' . urlencode($collection) . '&skip=';
						$paginator = $html->li(addslashes($html->link('', number_format($skip + 1) . '-' . number_format(min($skip + $objCount, $mo->mongo['count']))
												. ' of ' . $paginator)));
						if ($skip) { //back
							$paginator = $html->li(addslashes($html->link($url . max($skip - $objCount, 0), '&laquo;'))) . ' ' . $paginator;
						}
						if ($mo->mongo['count'] > ($objCount + $skip)) { //forward
							$paginator .= ' ' . $html->li(addslashes($html->link($url . ($skip + $objCount), '&raquo;')));
						}
					}

					$get = $_GET;
					$get['collection'] = urlencode($collection);
					$queryGet = $searchGet = $sortGet = $get;
					unset($sortGet['sort'], $sortGet['sortdir']);
					unset($searchGet['search'], $searchGet['searchField']);
					unset($queryGet['find']);


					echo $html->jsInline('var indexCount = 1;
				$(document).ready(function() {
					$("#mongo_rows .content-scroll").append("<ul class=\"pagination\">' . $paginator . '</ul>");
				});' . $dbcollnavJs);
					$jsShowIndexes = "javascript: $('#indexeslink').hide(); $('#indexes').show(); void(0);";

					/*
					 * Toolbar Options
					 */

					echo '<div id="mongo_rows">';

					$linkFindClass = isset($_GET['find']) ? ' running' : '';
					$query = ['id' => 'querylink',
						'class' => 'btn btn-default' . $linkFindClass,
						'data-popup' => 'true',
						'data-title' => 'Find Query',
						'data-body' => '#queryform',
						'data-button' => 'Run'
					];

					echo $html->link(null, 'find', $query);
					if (isset($index)) {
						echo $html->link($jsShowIndexes, 'indexes', ['id' => 'indexeslink', 'class' => 'btn btn-default']);
					}
					echo $html->link(null, 'export', ['id' => 'exportlink', 'class' => 'btn btn-default', 'data-popup' => 'true', 'data-title' => 'Export Options', 'data-body' => '#export', 'data-button' => 'hidden']);
					echo $html->link(null, 'import', ['id' => 'importlink', 'class' => 'btn btn-default', 'data-popup' => 'true', 'data-title' => 'Import Options', 'data-body' => '#import', 'data-button' => 'Import records into this collection']);

					if ($mo->mongo['colKeys']) {
						$colKeys = $mo->mongo['colKeys'];
						unset($colKeys['_id']);
						natcasesort($colKeys);

						$linkSortClass = isset($_GET['sort']) ? ' running' : '';
						$sort = ['id' => 'sortlink',
							'class' => 'btn btn-default' . $linkSortClass,
							'data-popup' => 'true',
							'data-title' => 'Sort Options',
							'data-body' => '#sortform',
							'data-button' => 'Sort'
						];

						echo $html->link(null, 'sort', $sort);
						?><div id="sortform" class="hidden">
							<select name="sort" id="sort" class="form-control" data-type="sort">
								<?php
								$defaultCols = ['_id' => '_id'];
								$colKeys = array_merge($defaultCols, $colKeys);
								foreach ($colKeys as $k => $v) :
									?>
									<option value="<?= $k ?>" <?= isset($_GET['sort']) && $_GET['sort'] === $k ? 'selected' : '' ?>><?= $v ?></option>
								<?php endforeach; ?>
							</select><br>
							<select name="sortdir" id="sortdir" class="form-control">
								<option value="1" <?= isset($_GET['sortdir']) && $_GET['sortdir'] == 1 ? 'selected' : '' ?>>asc</option>
								<option value="-1" <?= isset($_GET['sortdir']) && $_GET['sortdir'] == -1 ? 'selected' : '' ?>>desc</option>
							</select><br>
							<?php if (isset($_GET['sort'])) : ?>
								<a href="<?= $baseUrl ?>?db=<?= $db ?>&action=listRows&collection=<?= $collection ?>" class="btn btn-default">Clear sorting options</a>
							<?php endif; ?>
						</div><?php
					$linkSearchClass = isset($_GET['search']) ? ' running' : '';
					$search = ['id' => 'searchlink',
						'class' => 'btn btn-default' . $linkSearchClass,
						'data-popup' => 'true',
						'data-title' => 'Search Options',
						'data-body' => '#searchform',
						'data-button' => 'Search'
					];

					echo $html->link(null, 'search', $search);
							?><div id="searchform" class="hidden">
							<p class="alert alert-info">Valid search formats : exact-text, type-casted value, mongoid, text (with * wildcards), regex or JSON.</p>
							<select name="searchField" id="searchField" class="form-control" data-type="search">
								<?php
								$defaultCols = ['_id' => '_id'];
								$colKeys = array_merge($defaultCols, $colKeys);
								foreach ($colKeys as $k => $v) :
									?>
									<option value="<?= $k ?>" <?= isset($_GET['searchField']) && $_GET['searchField'] === $k ? 'selected' : '' ?>><?= $v ?></option>
								<?php endforeach; ?>
							</select><br>
							<input type="text" name="search" id="search" class="form-control input-lg"  placeholder="Search..." value="<?= isset($_GET['search']) ? $_GET['search'] : '' ?>">
							<br>
							<?php if (isset($_GET['search'])) : ?>
								<a href="<?= $baseUrl ?>?db=<?= $db ?>&action=listRows&collection=<?= $collection ?>" class="btn btn-default">Clear search options</a>
							<?php endif; ?>
						</div><?php
				}

				$remove = ['id' => 'removelink',
					'class' => 'btn btn-default',
					'data-popup' => 'true',
					'data-title' => 'Remove Query',
					'data-body' => '#removeform',
					'data-button' => 'Remove'
				];

				echo $html->link(null, 'remove', $remove);

				/*
				 * List Rows
				 */

				echo '<div class="content-scroll">';
				echo '<table class="table table-hover""><tbody>';
				$rowCount = (!isset($skip) ? 0 : $skip);
				$isChunksTable = (substr($collection, -7) == '.chunks');
				if ($isChunksTable) {
					$chunkUrl = $baseUrl . '?db=' . $dbUrl . '&action=listRows&collection=' . urlencode(substr($collection, 0, -7))
							. '.files#';
				}
				foreach ($mo->mongo['listRows'] as $row) {
					$showEdit = true;
					$id = $idString = $row['_id'];
					if (is_object($idString)) {
						$idString = '(' . get_class($idString) . ') ' . $idString;
						$idForUrl = serialize($id);
					} else if (is_array($idString)) {
						$idString = '(array) ' . json_encode($idString);
						$idForUrl = serialize($id);
					} else {
						$idForUrl = urlencode($id);
					}
					$idType = gettype($row['_id']);
					if ($isChunksTable && isset($row['data']) && is_object($row['data'])
							&& get_class($row['data']) == 'MongoBinData') {
						$showEdit = false;
						$row['data'] = $html->link($chunkUrl . $row['files_id'], 'MongoBinData Object', array('class' => 'Moa_Reference'));
					}
					$jdata = json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
					$data = str_replace('{', '[', ($jdata));
					$data = str_replace('}', ']', ($data));
					$data = str_replace(':', ' =>', ($data));

					echo ('<tr id="' . $row['_id'] . '">'
					. '<td><icon class="icon-caret-right"></icon></td>'
					. '<td class="hidden noclick">' . $html->link("javascript: removeObject('" . $idForUrl . "', '" . $idType
							. "'); void(0);", '<icon class="icon-remove-sign"></icon>', ['title' => 'Delete', 'class' => 'close']) . '</td> '
					. ($showEdit ? '<td class="noclick">' . $html->link($baseUrl . '?db=' . $dbUrl . '&collection=' . urlencode($collection)
									. '&action=editObject&_id=' . $idForUrl . '&idtype=' . $idType, '<icon class="icon-edit-sign"></icon>', array('title' => 'Edit')) . '</td> ' : ' <td><span title="Cannot edit objects containing MongoBinData">N/A</span></td> ')
					. '<td>' . $idString . '</td>' . '</tr><tr data-ref="' . $row['_id'] . '" class="hidden"><td colspan="4"><icon class="icon-eye-open"></icon> <a class="aotoggle">Obj View</a><pre>'
					. $jdata . '</pre><pre class="hidden">' . $data . '</pre></td></tr>');
				}
				echo '</tbody></table>';
				echo '</div>';
				if (!isset($idString)) {
					echo '<div class="errormessage">No records in this collection</div>';
				}
				echo '</div>';

				//edit object
			} else if (isset($mo->mongo['editObject'])) {

				$action = $baseUrl . '?db=' . $dbUrl . '&collection=' . urlencode($collection);
				if (isset($_GET['_id']))
					$action = $baseUrl . '?db=' . $dbUrl . '&collection=' . urlencode($collection) . '&action=editObject&_id=' . $_GET['_id'] . '&idtype=' . $_GET['idtype'];
				echo $form->open(array('action' => $action));
				if (isset($_GET['_id']) && $_GET['_id'] && ($_GET['idtype'] == 'object' || $_GET['idtype'] == 'array')) {
					$_GET['_id'] = unserialize($_GET['_id']);
					if (is_array($_GET['_id'])) {
						$_GET['_id'] = json_encode($_GET['_id']);
					}
				}
						?><h4><?= isset($_GET['_id']) && $_GET['_id'] ? get::htmlentities($_GET['_id']) : '[New Object]' ?></h4><?
				$textarea = array('name' => 'object', 'label' => '', 'rows' => "14", 'class' => 'form-control input-lg', 'addBreak' => false);
				$textarea['value'] = ($mo->mongo['editObject'] !== '' ? json_encode($mo->mongo['editObject'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{' . PHP_EOL . PHP_EOL . '}');
				echo $html->div($form->textarea($textarea)
						. $form->hidden(array('name' => 'action', 'value' => 'editObject')));
				echo $html->div($form->hidden(array('name' => 'db', 'value' => get::htmlentities($db))));
						?><a href="<?= $baseUrl . '?db=' . $dbUrl . '&action=listRows&collection=' . urlencode($collection); ?>" id="close-object" class="btn btn-default">Close</a>
					<button type="submit" id="edit-object" class="btn btn-<?= isset($_GET['saved']) ? 'success' : 'primary' ?> "><?= isset($_GET['_id']) && $_GET['_id'] ? (isset($_GET['saved']) ? 'Saved' : 'Save' ) : (isset($_GET['saved']) ? 'Saved' : 'Add' ) ?></button><?php
				echo $form->close();
			}
					?>

			</div>

		</div>
	</div>

	<!-- Modal Content -->
	<div id="new-collection" class="hidden">
		<form method = "GET" data-type ="collection" action="<?= $baseUrl ?>?db=<?= $db ?>">
			<input type="text" name="collection" class="form-control input-lg" placeholder="Collection name">
			<input type="hidden" name="action" value="createCollection">
			<input type="hidden" name="db" value="<?= $db ?>">
		</form>
	</div>

	<div id="new-database" class="hidden">
		<form method = "POST" data-type="database">
			<input type="hidden" name="db" value="new.database" />
			<input type="text" name="newdb" class="form-control input-lg" placeholder="Database name" />
		</form>
	</div>

	<ul id="export" class="hidden">
		<div>
			<?= $html->link(get::url(array('get' => true)) . '&export=limited', '<icon class="icon-download"></icon>&nbsp;Export exactly the results visible on this page', ['class' => "btn btn-success btn-lg btn-block"]); ?>
			<?= $html->link(get::url(array('get' => true)) . '&export=nolimit', '<icon class="icon-cloud-download"></icon>&nbsp;Export full results of this query <small>(ignoring limit and skip clauses)</small>', ['class' => "btn btn-default btn-lg btn-block"]); ?>
		</div>
	</ul>

	<div id="import" class="hidden">
		<?= $form->open(['upload' => true, 'role' => 'form']) ?>
		<fieldset>
			<div class="form-group">
				<label for="exampleInputFile">Browse / Choose your file</label>
				<input type="file" name="import" accept="application/json">
				<p class="help-block"><small>File ending with ".json".</small></p>
			</div>
			<div id="importmethod">
				<div class="checkbox well">
					<label>
						<input name="importmethod" type="radio" id="importmethod_insert" value="insert" checked="checked">&nbsp;Insert
						<small class="help-inline">&nbsp;-&nbsp;Skips over duplicate records</small>
					</label>
				</div>
				<div class="checkbox well">
					<label>
						<input name="importmethod" type="radio" id="importmethod_save" value="save">&nbsp;Save
						<small class="help-inline">&nbsp;-&nbsp;Overwrites duplicate records</small>
					</label>
				</div>
				<div class="checkbox well">
					<label>
						<input name="importmethod" type="radio" id="importmethod_update" value="update">&nbsp;Update
						<small class="help-inline">&nbsp;-&nbsp;Overwrites only records that currently exist (skips new objects)</small>
					</label>
				</div>
				<div class="checkbox well">
					<label>
						<input name="importmethod" type="radio" id="importmethod_batchInsert" value="batchInsert">&nbsp;Batch Insert
						<small class="help-inline">&nbsp;-&nbsp;Halt upon reaching first duplicate record (may result in partial dataset)</small>
					</label>
				</div>
			</div>
		</fieldset>
		<?= $form->close() ?>
	</div>

	<div id="queryform" class="hidden">
		<div class="alert alert-warning hidden">Invalid quotations, try to <a class="swopquote">correct</a>?</div>
		<div class="alert alert-danger hidden">Invalid json or dot-notation query, e.g. {"values.text.value":"ABC"}.</div>
		<textarea data-type="query" id="find" rows="4" class="form-control input-lg" placeholder="{ Enter query }"><?= isset($_GET['find']) ? $_GET['find'] : '' ?></textarea>
		<small class="help-block">Need help? Check out the documentation here : <a href="http://docs.mongodb.org/manual/reference/method/db.collection.find/">Mongo Query Find</a></small>
		<?php if (isset($_GET['find'])) : ?>
			<a href="<?= $baseUrl ?>?db=<?= $db ?>&action=listRows&collection=<?= $collection ?>" class="btn btn-default">Clear query find</a>
		<?php endif; ?>
	</div>

	<div id="removeform" class="hidden">
		<form action="<?= $baseUrl ?>?db=<?= $db ?>&action=listRows&collection=<?= $collection ?>&remove=query&request=<?= time() ?>" method="post" data-type="removeQuery">
			<p class="alert alert-info">You can also remove objects manually by closing this panel and clicking the 'Edit' button in the top right-hand corner. Please take care when removing via query.</p>
			<div class="alert alert-warning hidden">Invalid quotations, try to <a class="swopquote">correct</a>?</div>
			<div class="alert alert-danger hidden">Invalid json or dot-notation query, try again.</div>
			<textarea id="removeQuery" rows="2" name="remove" class="form-control input-lg" placeholder="{ Remove query }"><?= isset($_POST['remove']) ? $_POST['remove'] : '' ?></textarea>
			<small class="help-block">Need help? Check out the documentation here : <a href="http://docs.mongodb.org/manual/reference/method/db.collection.remove/">Mongo Query Remove</a></small>
		</form>
	</div>

	<div id="new-object" class="hidden">
		<div class="alert alert-warning hidden">Invalid quotations, try to <a class="swopquote">correct</a>?</div>
		<div class="alert alert-danger hidden">Your object is invalid so will be saved a string, i.e. {0 : "<span class="string">string</span>"}. Press 'Add' again to continue or <strong><a class="objectclose">dismiss</a></strong> to edit.</div>

		<form action="<?= $baseUrl ?>?db=<?= $db ?>&collection=<?= $collection ?>" method="post" data-type="object">
			<fieldset>
				<textarea name="object" id="newObj" class="form-control input-lg" rows="10">{ }</textarea>
				<input type="hidden" name="action" value="editObject">
				<input type="hidden" name="db" value="<?= $db ?>">
			</fieldset>
		</form>
	</div>


	<!-- Modal -->
	<div class="modal fade" id="modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
					<h4 class="modal-title"></h4>
				</div>
				<div class="modal-body"></div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
					<button type="button" id="btn-main" class="btn btn-primary ">Add</button>
				</div>
			</div><!-- /.modal-content -->
		</div><!-- /.modal-dialog -->
	</div><!-- /.modal -->



	<div class="footer">
        <p><small>Help contribute to <a href="https://github.com/lovetheidea/MoaDB">moaDB</a>. Report any bugs <a href="https://github.com/lovetheidea/MoaDB/issues">here.</a> |  Maintained and designed by lovetheidea</small></p>
	</div>

</div> <!-- /container -->
<script>
	//<!-- original public functions -->

	var mo = {};
	var urlEncode = function(str) {
		return escape(str).replace(/\+/g, "%2B").replace(/%20/g, "+").replace(/\*/g, "%2A").replace(/\//g, "%2F").replace(/@/g, "%40");
	};
	
	//TODO
	var repairDatabase = function(db) {
		if(confirm("Are you sure that you want to repair and compact the " + db + " database?")) {
			//window.location.replace("' . $baseUrl . '?db=' . $dbUrl . '&action=repairDb");
		}
	};
	
	$(document).delegate('textarea', 'keydown', function(e) { 
 		var keyCode = e.keyCode || e.which; 
 
 		if (keyCode == 9) { 
 			e.preventDefault(); 
 			var start = $(this).get(0).selectionStart;
 			var end = $(this).get(0).selectionEnd;

			// set textarea value to: text before caret + tab + text after caret
 			$(this).val($(this).val().substring(0, start)
 						+ "\t"
 						+ $(this).val().substring(end));

 			// put caret at right position again
 			$(this).get(0).selectionStart = 
 			$(this).get(0).selectionEnd = start + 1;
		} 
	});
<?php if (!$hasDB) : ?>
		var dropDatabase = function(db) {
			if(confirm("Are you sure that you want to drop the " + db + " database?")) {
				if(confirm("All the collections in the " + db + " database will be lost along with all the data within them!"
					+ '\n\nAre you 100% sure that you want to drop this database?'
					+ "\n\nLast chance to cancel!")){
					window.location.replace("<?= $baseUrl ?>" + "?db=" + db + "&action=dropDb");
				}
			}
		};
<?php elseif ($hasCollection) : ?>
		var removeObject = function(_id, idType) {
			if(confirm("Are you sure that you want to delete this " + _id + " object?")) {
				window.location.replace("<?= $baseUrl . '?db=' . $db . '&collection=' . urlencode($collection)
	. '&action=removeObject&_id='
	?>" + urlEncode(_id) + "&idtype=" + idType);
							}
						};
<?php else : ?>
		var collectionDrop = function(collection) {
			if(confirm("Are you sure that you want to drop " + collection + "?")){
				window.location.replace("<?= $baseUrl . '?db=' . $db . '&collection=' ?>" + collection + "<?= '&action=dropCollection' ?>");
							}
						};
<?php endif; ?>
</script>

