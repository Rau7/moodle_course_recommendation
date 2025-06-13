# Course Recommendation Block for Moodle

## Overview

The Course Recommendation block is a Moodle plugin that recommends courses to users based on collaborative filtering and course metadata analysis. It provides personalized course recommendations without using AI, making it lightweight and privacy-friendly.

## Features

- Recommends up to 6 courses based on user enrollment patterns
- Works with custom database table prefixes
- Compatible with Moodle 4.0+
- Fully responsive design for both web and mobile interfaces
- Moodle Mobile App support

## Recommendation Logic

The plugin uses a hybrid recommendation approach:

### 1. Collaborative Filtering

- Identifies the current user's enrolled courses
- Finds similar users who have taken at least one of the same courses
- Discovers courses those similar users have taken that the current user hasn't
- Scores courses based on enrollment frequency (how many similar users have taken each course)

### 2. Content-Based Filtering

- Boosts recommendation scores for courses in the same categories as the user's enrolled courses
- This ensures recommendations are contextually relevant to the user's interests

### 3. Scoring System

- Base score: Number of similar users enrolled in a course × 10
- Category bonus: +50 points if the course is in a category the user has already taken courses from
- Courses are then ranked by total score, and the top 6 are displayed

## Installation

1. Download the plugin
2. Extract the folder and rename it to "course_recommendation"
3. Upload the folder to your Moodle blocks directory: `/blocks/`
4. Visit the notifications page as an administrator to complete the installation

## Requirements

- Moodle 4.0 or higher
- Sufficient user enrollment data for effective recommendations

## Usage

The block can be added to any page where blocks are supported:

1. Turn editing on
2. Click "Add a block"
3. Select "Course Recommendations"

The block will automatically display personalized course recommendations for the logged-in user.

## Privacy

This plugin does not store any personal data. It dynamically generates recommendations based on existing enrollment data in the Moodle database.

## License

This plugin is licensed under the GNU GPL v3 or later. See the LICENSE file for details.

## Credits

Developed by Alp Toker © 2025
