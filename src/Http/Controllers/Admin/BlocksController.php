<?php

namespace A17\CmsToolkit\Http\Controllers\Admin;

use A17\CmsToolkit\Repositories\BlockRepository;

class BlocksController extends Controller
{
    public function preview(BlockRepository $blockRepository)
    {
        $blocksCollection = collect();
        $childBlocksList = collect();

        $block = $blockRepository->buildFromCmsArray(request()->all());

        foreach ($block['blocks'] as $childKey => $childBlocks) {
            foreach ($childBlocks as $index => $childBlock) {
                $childBlock = $blockRepository->buildFromCmsArray($childBlock, true);
                $childBlock['child_key'] = $childKey;
                $childBlock['position'] = $index + 1;

                $childBlocksList->push($childBlock);
            }
        }

        $block['blocks'] = $childBlocksList;

        $newBlock = $blockRepository->createForPreview($block);

        $newBlock->id = 1;

        $blocksCollection->push($newBlock);

        $block['blocks']->each(function ($childBlock) use ($newBlock, $blocksCollection, $blockRepository) {
            $childBlock['parent_id'] = $newBlock->id;
            $newChildBlock = $blockRepository->createForPreview($childBlock);
            $blocksCollection->push($newChildBlock);
        });

        $renderedBlocks = $blocksCollection->where('parent_id', null)->map(function ($block) use ($blocksCollection) {
            if (config('cms-toolkit.block-editor.block_preview_render_childs') ?? true) {
                $childBlocks = $blocksCollection->where('parent_id', $block->id);
                $renderedChildViews = $childBlocks->map(function ($childBlock) {
                    $view = $this->getBlockView($childBlock->type);
                    return view($view)->with('block', $childBlock)->render();
                })->implode('');
            }

            $block->childs = $blocksCollection->where('parent_id', $block->id);

            $view = $this->getBlockView($block->type);

            return view($view)->with('block', $block)->render() . ($renderedChildViews ?? '');
        })->implode('');

        $view = view(config('cms-toolkit.block_editor.block_single_layout'));

        $view->getFactory()->inject('content', $renderedBlocks);

        return html_entity_decode($view);
    }

    private function getBlockView($blockType)
    {
        $view = config('cms-toolkit.block_editor.block_views_path') . '.' . $blockType;

        $customViews = config('cms-toolkit.block_editor.block_views_mappings');

        if (array_key_exists($blockType, $customViews)) {
            $view = $customViews[$blockType];
        }

        return $view;
    }

}
