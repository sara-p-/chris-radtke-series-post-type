import { __ } from '@wordpress/i18n'
import { RawHTML } from '@wordpress/element'
import { useBlockProps } from '@wordpress/block-editor'
import { useEntityProp } from '@wordpress/core-data'
import { Notice } from '@wordpress/components'
import { autop } from '@wordpress/autop'
import { safeHTML } from '@wordpress/dom'

export default function Edit({ context }) {
  const { postId, postType } = context

  const [meta] = useEntityProp('postType', postType, 'meta', postId)
  const press = meta?._work_press

  const blockProps = useBlockProps({ className: 'work-press' })

  if (!postId) {
    return (
      <div {...blockProps}>
        {/* <Notice status='info' isDismissible={false}>
          {__(
            'The work press will display when viewing a Work post.',
            'work',
          )}
        </Notice> */}
        <p>Work Press placeholder...</p>
        <p>
          If there are press items for the work, they will appear here on the
          front end.
        </p>
      </div>
    )
  }

  if (!press) {
    return (
      <div {...blockProps}>
        <Notice status='info' isDismissible={false}>
          {__('No press set for this post.', 'work')}
        </Notice>
      </div>
    )
  }

  return (
    <div {...blockProps}>
      <RawHTML>{safeHTML(autop(press))}</RawHTML>
    </div>
  )
}
