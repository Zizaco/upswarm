<?php

namespace Upswarm;

use Evenement\EventEmitter;
use Evenement\EventEmitterInterface;
use React\EventLoop\LoopInterface;
use React\Filesystem\Filesystem;

/**
 * Reads 'topology.json' file and emit an event if it have changed.
 */
class TopologyReader extends EventEmitter implements EventEmitterInterface
{
    /**
     * Constructor
     * @param LoopInterface $loop     Main EventLoop.
     * @param string        $filename Topology file that will be read.
     */
    public function __construct(LoopInterface $loop, string $filename = 'topology.json')
    {
        $this->loop = $loop;
        $this->filesystem = Filesystem::create($loop);
        $this->filename = $filename;
        $this->file = $this->filesystem->file($filename);
        $this->fileLastModification = null;

        $this->registerEvents();
    }

    /**
     * Register events regarding topology.json file
     *
     * @return void
     */
    public function registerEvents()
    {
        $this->loop->addTimer(1, function () {
            $this->lookForChanges();
        });

        $this->loop->addTimer(6, function () {
            $this->loop->addPeriodicTimer(6, function () {
                $this->lookForChanges();
            });
        });
    }

    /**
     * Test if the topology.json was modified.
     *
     * @return void
     */
    public function lookForChanges()
    {
        try {
            $this->file->exists()->then(function () {
                $this->file->time()->then(function ($time) {
                    if ($this->fileLastModification != $time['mtime'] ?? null) {
                        $this->fileLastModification = $time['mtime'] ?? null;
                        $this->updateTopology();
                    }
                });
            }, function () {
                $this->emit('error', ["Unable to read '{$this->filename}'. Make sure the file is readable."]);
            });
        } catch (\Exception $e) {
            $this->emit('error', ["Unable to read '{$this->filename}'. Make sure the file is readable."]);
        }
    }

    /**
     * Emmits 'update' event with the service topology contained in topology.json.
     *
     * @return void
     */
    public function updateTopology()
    {
        $this->emit('info', ["Reading '{$this->filename}'."]);

        $this->file->getContents()->then(function ($contents) {
            $services = json_decode($contents, true);

            if (! is_array($services)) {
                $this->emit('error', ["Error while parsing 'topology.json'. The file content is not a valid json."]);
                return;
            }

            $this->emit('update', [$services['services'] ?? []]);
        });
    }
}
