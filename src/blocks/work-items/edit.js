import { __ } from '@wordpress/i18n'
import { RawHTML, useMemo } from '@wordpress/element'
import { useBlockProps } from '@wordpress/block-editor'
import { useEntityProp } from '@wordpress/core-data'
import { useSelect } from '@wordpress/data'
import { store as coreStore } from '@wordpress/core-data'
import { Notice } from '@wordpress/components'
import { autop } from '@wordpress/autop'
import { safeHTML } from '@wordpress/dom'

export default function Edit({ context }) {
  const { postId, postType } = context

  const [meta] = useEntityProp('postType', postType, 'meta', postId)

  // _work_items: array of { title, item_years, image_id, item_description }
  // Stored as a JSON string (or possibly a native array — handle both).
  const items = useMemo(() => {
    const raw = meta?._work_items
    if (!raw) {
      return []
    }
    if (Array.isArray(raw)) {
      return raw
    }
    try {
      const parsed = JSON.parse(raw)
      return Array.isArray(parsed) ? parsed : []
    } catch (e) {
      return []
    }
  }, [meta?._work_items])

  // Resolve all image IDs in one selector.
  const images = useSelect(
    (select) => {
      const map = {}
      items.forEach((item) => {
        const id = parseInt(item?.image_id, 10)
        if (id) {
          const media = select(coreStore).getMedia(id)
          map[id] =
            media?.media_details?.sizes?.large?.source_url ??
            media?.source_url ??
            null
        }
      })
      return map
    },
    [items],
  )

  const blockProps = useBlockProps({ className: 'work-items' })

  if (!postId) {
    return (
      <div {...blockProps}>
        <Notice status='info' isDismissible={false}>
          {__('Work items will display when viewing a Work post.', 'work')}
        </Notice>
      </div>
    )
  }

  if (!items.length) {
    return (
      <div {...blockProps}>
        <Notice status='info' isDismissible={false}>
          {__('No work items set for this post.', 'work')}
        </Notice>
      </div>
    )
  }

  return (
    <div {...blockProps}>
      {items.map((item, index) => {
        const imageId = parseInt(item?.image_id, 10)
        const imageUrl = imageId ? images[imageId] : null

        return (
          <article className='work-items__item' key={index}>
            <div className='work-items__header'>
              <div className='work-items__title-box'>
                <h3 className='work-items__title'>
                  {item?.title && (
                    <span className='work-items__title'>{item.title} </span>
                  )}
                  {item?.item_years && (
                    <span className='work-items__years'>
                      | {item.item_years}
                    </span>
                  )}
                </h3>
              </div>
              {item?.item_description && (
                <div className='work-items__description'>
                  <RawHTML>{safeHTML(autop(item.item_description))}</RawHTML>
                </div>
              )}
            </div>
            {imageUrl && (
              <img
                className='work-items__image'
                src={imageUrl}
                alt={item?.title ?? ''}
              />
            )}
          </article>
        )
      })}
    </div>
  )
}
