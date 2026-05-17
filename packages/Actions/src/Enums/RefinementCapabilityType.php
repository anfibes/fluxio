<?php

namespace Fluxio\Actions\Enums;

enum RefinementCapabilityType: string
{
    case ReplaceField            = 'replace_field';
    case ClearField              = 'clear_field';
    case AppendCollectionItem    = 'append_collection_item';
    case RemoveCollectionItem    = 'remove_collection_item';
    case ReplaceCollectionItem   = 'replace_collection_item';
    case ResolveAmbiguity        = 'resolve_ambiguity';
    case ContextualReference     = 'contextual_reference';
}
