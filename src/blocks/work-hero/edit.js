import { __ } from '@wordpress/i18n'
import { useMemo } from '@wordpress/element'
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor'
import { useEntityProp } from '@wordpress/core-data'
import { useSelect } from '@wordpress/data'
import { Notice } from '@wordpress/components'

const TEMPLATE = [['core/post-title', { level: 1 }]]

export default function Edit({ context }) {
  const { postId, postType } = context

  const [meta] = useEntityProp('postType', postType, 'meta', postId)

  // _work_hero_bg is a JSON string:
  // { image_id, position_x, position_y, size, repeat, attachment }
  const bg = useMemo(() => {
    if (!meta?._work_hero_bg) {
      return null
    }
    try {
      const parsed = JSON.parse(meta._work_hero_bg)
      return parsed && typeof parsed === 'object' ? parsed : null
    } catch (e) {
      return null
    }
  }, [meta?._work_hero_bg])

  const imageId = bg?.image_id ? parseInt(bg.image_id, 10) : null

  const bgUrl = useSelect(
    (select) => {
      if (!imageId) {
        return null
      }
      return select('core').getMedia(imageId)?.source_url ?? null
    },
    [imageId],
  )

  const bgStyles = bgUrl
    ? {
        backgroundImage: `url(${bgUrl})`,
        backgroundPosition: `${bg?.position_x ?? 'center'} ${
          bg?.position_y ?? 'center'
        }`,
        backgroundSize: bg?.size || 'cover',
        backgroundRepeat: bg?.repeat || 'no-repeat',
        backgroundAttachment: bg?.attachment || 'scroll',
      }
    : undefined

  const blockProps = useBlockProps({
    className: 'work-hero editor-work-hero',
    style: bgStyles,
  })

  const innerBlocksProps = useInnerBlocksProps(
    { className: 'editor-work-hero__inner' },
    {
      template: TEMPLATE,
      templateLock: false,
    },
  )

  return (
    <div {...blockProps}>
      {/* {!bgUrl && (
        <Notice status='info' isDismissible={false}>
          {postId
            ? __('No hero background image set for this post.', 'work')
            : __(
                'Background image will display when viewing a Work post.',
                'work',
              )}
        </Notice>
      )} */}
      <div {...innerBlocksProps} />
    </div>
  )
}
