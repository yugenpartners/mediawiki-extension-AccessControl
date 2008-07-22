<?php
	$wgAccesscontrolDebug = true;
	$wgAccesscontrolDebugFile = "C:/Development/testwiki/config/hooks.txt";

	$wgHooks['GetBlockedStatus'][] = 'hookGetBlockedStatus';
	$wgHooks['MagicWordMagicWords'][] = 'hookMagicWordMagicWords';
	$wgHooks['MagicWordwgVariableIDs'][] = 'hookMagicWordwgVariableIDs';
	$wgHooks['OutputPageParserOutput'][] = 'hookOutputPageParserOutput';
	$wgHooks['ParserAfterStrip'][] = 'hookParserAfterStrip';
	$wgHooks['ParserAfterTidy'][] = 'hookParserAfterTidy';
	$wgHooks['ParserBeforeInternalParse'][] = 'hookParserBeforeInternalParse';
	$wgHooks['ParserBeforeTidy'][] = 'hookParserBeforeTidy';
	$wgHooks['ParserClearState'][] = 'hookParserClearState';
	$wgHooks['ParserGetVariableValueSwitch'][] = 'hookParserGetVariableValueSwitch';
	$wgHooks['ParserGetVariableValueTs'][] = 'hookParserGetVariableValueTs';
	$wgHooks['ParserGetVariableValueVarCache'][] = 'hookParserGetVariableValueVarCache';
	$wgHooks['userCan'][] = 'hookuserCan2';


	function debugme($input)
	{
		global $wgAccesscontrolDebug;
		global $wgAccesscontrolDebugFile;

		if ($wgAccesscontrolDebug)
		{
			$f = fopen($wgAccesscontrolDebugFile, "a+");
			fputs($f, $input."\r\n");
			fclose($f);
		}
	}

	function hookGetBlockedStatus() { debugme( "hookGetBlockedStatus" ); }
	function hookMagicWordMagicWords() { debugme( "hookMagicWordMagicWords" ); }
	function hookMagicWordwgVariableIDs() { debugme( "hookMagicWordwgVariableIDs" ); }
	function hookOutputPageParserOutput(&$outputPage, $parserOutput) 
	{ 
		debugme( "hookOutputPageParserOutput" ); 
		debugme( $parserOutput->mText );
	}
	function hookParserAfterStrip() { debugme( "hookParserAfterStrip" ); }
	function hookParserAfterTidy() { debugme( "hookParserAfterTidy" ); }
	function hookParserBeforeInternalParse() { debugme( "hookParserBeforeInternalParse" ); }
	function hookParserBeforeTidy() { debugme( "hookParserBeforeTidy" ); }
	function hookParserClearState() { debugme( "hookParserClearState" ); }
	function hookParserGetVariableValueSwitch() { debugme( "hookParserGetVariableValueSwitch" ); }
	function hookParserGetVariableValueTs() { debugme( "hookParserGetVariableValueTs" ); }
	function hookParserGetVariableValueVarCache() { debugme( "hookParserGetVariableValueVarCache" ); }
	function hookRecentChange_save() { debugme( "hookRecentChange_save" ); }
	function hookuserCan2() { debugme( "hookuserCan" ); }
?>
