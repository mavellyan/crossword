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
    case MISSING_CLUE = 'missing_clue';
    case MISSING_TITLE = 'missing_title';
    case MISSING_SOLUTION = 'missing_solution';
    case MISSING_DEFINITION = 'missing_definition';
    case DUPLICATE_ENTRY = 'duplicate_entry';
    case MAIN_SOLUTION_MISMATCH = 'main_solution_mismatch';
    case GUIDED_LAYOUT_DIRECTION_MISMATCH = 'guided_layout_direction_mismatch';
    case GUIDED_LAYOUT_POSITION_MISMATCH = 'guided_layout_position_mismatch';
    case GUIDED_LAYOUT_MAIN_COLUMN_MISMATCH = 'guided_layout_main_column_mismatch';
    case CLUE_TOPIC_MISMATCH = 'clue_topic_mismatch';
}