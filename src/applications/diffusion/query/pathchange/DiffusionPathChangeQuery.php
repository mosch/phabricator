<?php

final class DiffusionPathChangeQuery {

  private $request;

  final private function __construct() {
    // <private>
  }

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    $query = new DiffusionPathChangeQuery();
    $query->request = $request;

    return $query;
  }

  final protected function getRequest() {
    return $this->request;
  }

  final public function loadChanges() {
    return $this->executeQuery();
  }

  protected function executeQuery() {

    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $commit = $drequest->loadCommit();

    $raw_changes = queryfx_all(
      $repository->establishConnection('r'),
      'SELECT c.*, p.path pathName, t.path targetPathName,
          i.commitIdentifier targetCommitIdentifier
        FROM %T c
          LEFT JOIN %T p ON c.pathID = p.id
          LEFT JOIN %T t ON c.targetPathID = t.id
          LEFT JOIN %T i ON c.targetCommitID = i.id
        WHERE c.commitID = %d AND isDirect = 1',
      PhabricatorRepository::TABLE_PATHCHANGE,
      PhabricatorRepository::TABLE_PATH,
      PhabricatorRepository::TABLE_PATH,
      $commit->getTableName(),
      $commit->getID());

    $changes = array();

    $raw_changes = isort($raw_changes, 'pathName');
    foreach ($raw_changes as $raw_change) {
      $type = $raw_change['changeType'];
      if ($type == DifferentialChangeType::TYPE_CHILD) {
        continue;
      }

      $change = new DiffusionPathChange();
      $change->setPath(ltrim($raw_change['pathName'], '/'));
      $change->setChangeType($raw_change['changeType']);
      $change->setFileType($raw_change['fileType']);
      $change->setCommitIdentifier($commit->getCommitIdentifier());

      $change->setTargetPath(ltrim($raw_change['targetPathName'], '/'));
      $change->setTargetCommitIdentifier($raw_change['targetCommitIdentifier']);

      $id = $raw_change['pathID'];
      $changes[$id] = $change;
    }

    // Deduce the away paths by examining all the changes.

    $away = array();
    foreach ($changes as $change) {
      if ($change->getTargetPath()) {
        $away[$change->getTargetPath()][] = $change->getPath();
      }
    }
    foreach ($changes as $change) {
      if (isset($away[$change->getPath()])) {
        $change->setAwayPaths($away[$change->getPath()]);
      }
    }


    return $changes;
  }
}
