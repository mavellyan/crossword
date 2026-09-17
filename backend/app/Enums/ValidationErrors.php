<?php

namespace App\Enums;

enum ValidationErrors: string {
    case LETTER_CONFLICT = 'letter_conflict';
    case NEGATIVE_COORDINATE = 'negative_coordinate';
    case ANSWER_TOO_LONG = 'answer_too_long';
    case ANSWER_TOO_SHORT = 'answer_too_short';
    case OUT_OF_BOUNDS = 'out_of_bounds';
    case SAME_DIRECTION_OVERLAP = 'same_direction_overlap';
    case BLOCKED_ENDPOINT = 'blocked_endpoint';
    case SIDE_ADJACENCY = 'side_adjacency';
    case DISCONNECTED_ENTRY = 'disconnected_entry';
    case TOO_FEW_ENTRIES = 'too_few_entries';
    case DISCONNECTED_LAYOUT = 'disconnected_layout';
}