<?php
// SPDX-License-Identifier: MIT
// Copyright (c) 2026 Ismail Habib
add_action('wp_enqueue_scripts',function(){wp_enqueue_style('projectroom',get_stylesheet_uri(),[],'1.0.0');});
add_theme_support('title-tag');
