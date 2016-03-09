<?php
/**
 * Copyright (c) Enalean, 2016. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'autoload.php';
require_once 'constants.php';

use Tuleap\HudsonSvn\Plugin\HudsonSvnPluginInfo;
use Tuleap\HudsonSvn\ContinuousIntegrationCollector;
use Tuleap\Svn\Repository\RepositoryManager;
use Tuleap\Svn\Dao as SvnDao;
use Tuleap\HudsonSvn\Job\Dao as JobDao;
use Tuleap\HudsonSvn\Job\Manager;
use Tuleap\HudsonSvn\Job\Factory;

class hudson_svnPlugin extends Plugin {

    public function __construct($id) {
        parent::__construct($id);
        $this->setScope(self::SCOPE_SYSTEM);

        $this->_addHook('cssfile');
        $this->_addHook('javascript_file');

        $this->_addHook('collect_ci_triggers');
        $this->_addHook('save_ci_triggers');
        $this->_addHook('update_ci_triggers');
        $this->_addHook('delete_ci_triggers');
    }

    /**
     * @see Plugin::getDependencies()
     */
    public function getDependencies() {
        return array('svn', 'hudson');
    }

    /**
     * @return HudsonSvnPluginInfo
     */
    public function getPluginInfo() {
        if (!$this->pluginInfo) {
            $this->pluginInfo = new HudsonSvnPluginInfo($this);
        }
        return $this->pluginInfo;
    }

    public function cssfile($params) {
        if (strpos($_SERVER['REQUEST_URI'], HUDSON_BASE_URL) === 0) {
            echo '<link rel="stylesheet" type="text/css" href="'.$this->getThemePath().'/css/style.css" />';
        }
    }

    public function javascript_file($params) {
        if (strpos($_SERVER['REQUEST_URI'], HUDSON_BASE_URL) === 0) {
            echo '<script type="text/javascript" src="'.$this->getPluginPath().'/scripts/form.js"></script>';
        }
    }

    public function collect_ci_triggers($params) {
        $collector = new ContinuousIntegrationCollector(
            $this->getRenderer(),
            $this->getRepositoryManager(),
            new JobDao(),
            $this->getJobFactory()
        );

        $project_id = $params['group_id'];
        $project    = $this->getProjectManager()->getProject($project_id);
        $job_id     = isset($params['job_id']) ? $params['job_id'] : null;

        $params['services'][] = $collector->collect($project, $job_id);
    }

    private function getJobFactory() {
        return new Factory(new JobDao());
    }

    private function getRenderer() {
        return TemplateRendererFactory::build()->getRenderer(HUDSON_SVN_BASE_DIR.'/templates');
    }

    private function getRepositoryManager() {
        $dao = new SvnDao();

        return new RepositoryManager($dao, $this->getProjectManager());
    }

    private function getProjectManager() {
        return ProjectManager::instance();
    }

    private function getJobManager() {
        return new Manager(new JobDao(), $this->getRepositoryManager(), new SVNPathsUpdater());
    }

    private function isJobValid($job_id) {
        return isset($job_id) && !empty($job_id);
    }

    private function isRequestWellFormed(array $params) {
        return $this->isJobValid($params['job_id']) &&
               isset($params['request']) &&
               !empty($params['request']);
    }

    private function isPluginConcerned(array $params) {
        return $params['request']->get('hudson_use_plugin_svn_trigger_checkbox');
    }

    public function save_ci_triggers($params) {
        if ($this->isRequestWellFormed($params) && $this->isPluginConcerned($params)) {
            $this->getJobManager()->save($params);
        }
    }

    public function update_ci_triggers($params) {
        $params['job_id'] = $params['request']->get('job_id');
        if ($this->isRequestWellFormed($params) && $this->isPluginConcerned($params)) {
            $vRepoId = new Valid_Uint('hudson_use_plugin_svn_trigger');
            $vRepoId->required();
            if ($params['request']->valid($vRepoId)) {
                $this->getJobManager()->save($params);
            } else {
                $this->getJobManager()->delete($params['job_id']);
            }
        }
    }

    public function delete_ci_triggers($params) {
        if ($this->isJobValid($params['job_id'])) {
            if (! $this->getJobManager()->delete($params['job_id'])) {
                $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('plugin_hudson_svn','ci_trigger_not_deleted'));
            }
        }
    }

}