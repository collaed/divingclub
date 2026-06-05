<?php

declare(strict_types=1);

namespace App\Enums;

enum HomogeneityFactorType: string
{
    case Air = 'air';
    case Ease = 'ease';
    case Intent = 'intent';
    case Pace = 'pace';
    case TaskConflict = 'task_conflict';
    case AutonomyReturn = 'autonomy_return';
    case FragilityCluster = 'fragility_cluster';
    case RecencyCluster = 'recency_cluster';
    case GroupTaskConflict = 'group_task_conflict';
    case IntentDispersion = 'intent_dispersion';
    case DeepAirPenalty = 'deep_air_penalty';
    case ColdFragility = 'cold_fragility';
    case JuniorLoad = 'junior_load';
    case Input = 'input';
}
