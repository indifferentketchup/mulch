<?php

namespace IndifferentKetchup\CodexPz\Pattern\ProjectZomboid;

/**
 * Regex constants for the Project Zomboid chat.txt format.
 *
 * Two row variants share the file: bracket-level chat-engine events
 * (e.g. [time][info] message) and bare server-alert messages
 * (e.g. [time] Server alert ...). The LINE pattern accepts both via an
 * optional non-capturing wrapper around the level.
 *
 * LINE captures, in order:
 *   1. time  (DD-MM-YY HH:MM:SS.mmm)
 *   2. level (info | warn | error | empty for server alerts)
 */
class ChatPattern
{
    public const string LINE = '/^\[(\d{2}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3})\](?:\[(\w+)\])?\s+.*$/';

    public const string CHAT_MESSAGE = '/ChatMessage\{chat=(?<channel>\w+), author=\'(?<author>[^\']+)\', text=\'(?<text>.*?)\'\}/';

    public const string SERVER_ALERT = '/^Server alert message:\s*\'(?<text>.*?)\'\s+sent\.\.?$/';
}
