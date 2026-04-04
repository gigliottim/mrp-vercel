<?php
// DEPRECATED: Este archivo fue migrado a agenteAI/backend/controllers/AgentController.php
// Este archivo mantiene compatibilidad mediante class_alias
require_once BASE_PATH . '/agenteAI/backend/controllers/AgentController.php';
class_alias(\App\AgenteAI\Backend\Controllers\AgentController::class, \App\Controllers\agent_AgentController::class);
