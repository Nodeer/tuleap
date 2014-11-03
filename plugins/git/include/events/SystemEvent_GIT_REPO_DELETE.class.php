<?php
/**
  * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
  *
  * This file is a part of Codendi.
  *
  * Codendi is free software; you can redistribute it and/or modify
  * it under the terms of the GNU General Public License as published by
  * the Free Software Foundation; either version 2 of the License, or
  * (at your option) any later version.
  *
  * Codendi is distributed in the hope that it will be useful,
  * but WITHOUT ANY WARRANTY; without even the implied warranty of
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  * GNU General Public License for more details.
  *
  * You should have received a copy of the GNU General Public License
  * along with Codendi. If not, see <http://www.gnu.org/licenses/
  */
require_once('common/system_event/SystemEvent.class.php');
/**
 * Description of SystemEvent_GIT_REPO_DELETE
 *
 * @author gstorchi
 */
class SystemEvent_GIT_REPO_DELETE extends SystemEvent {
    const NAME = 'GIT_REPO_DELETE';

    /** @var GitRepositoryFactory */
    private $repository_factory;

    /** @var Git_Mirror_ManifestManager */
    private $manifest_manager;

    /** @var Logger */
    private $logger;

    public function injectDependencies(
        GitRepositoryFactory $repository_factory,
        Git_Mirror_ManifestManager $manifest_manager,
        Logger $logger
    ) {
        $this->repository_factory = $repository_factory;
        $this->manifest_manager   = $manifest_manager;
        $this->logger             = $logger;
    }

    public function process() {
        $parameters   = $this->getParametersAsArray();
        //project id
        $projectId    = 0;
        if ( !empty($parameters[0]) ) {
            $projectId = (int) $parameters[0];
        } else {
            $this->error('Missing argument project id');
            return false;
        }
        //repo id
        $repositoryId = 0;
        if ( !empty($parameters[1]) ) {
            $repositoryId = (int) $parameters[1];
        } else {
            $this->error('Missing argument repository id');
            return false;
        }

        $repository = $this->repository_factory->getDeletedRepository($repositoryId);
        if ($repository->getProjectId() != $projectId) {
            $this->error('Bad project id');
            return false;
        }

        return $this->deleteRepo($repository, $projectId, $parameters);
    }

    private function deleteRepo(GitRepository $repository) {
        try {
            $this->logger->debug("Deleting repository ". $repository->getPath());
            $this->manifest_manager->triggerDelete($repository);
            $repository->delete();
        } catch (Exception $e) {
            $this->error($e->getMessage());
            return false;
        }
        $this->done();
        return true;
    }

    public function verbalizeParameters($with_link) {
        return $this->parameters;
    }

}
