<?php
function checkPid($prog)
    {
        $lockfile = sys_get_temp_dir(). '/'.$prog.'.pid';
        if (($pid = @file_get_contents($lockfile)))
        {
            if (posix_getsid($pid) !== false)
            {
                $hbfile = sys_get_temp_dir(). '/'.$prog.'.hb';
                if (file_exists($hbfile) && time() - filemtime($hbfile) > 120)
                {
                    unlink($hbfile);
                    posix_kill($pid, 9);
                }
                else
                    exit;
            }
        }
        file_put_contents($lockfile, getmypid());
    }
?>