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
  const description = meta?._work_description

  const blockProps = useBlockProps({ className: 'work-statement' })

  if (!postId) {
    return (
      <div {...blockProps}>
        {/* <Notice status='info' isDismissible={false}>
          {__(
            'The work description will display when viewing a Work post.',
            'work',
          )}
        </Notice> */}
        <p>Work Statement placeholder...</p>
        <p>
          Lorem Ipsum is simply dummy text of the printing and typesetting
          industry. Lorem Ipsum has been the industry's standard dummy text ever
          since 1966, when designers at Letraset and James Mosley, the librarian
          at St Bride Printing Library, took a 1914 Cicero translation and
          scrambled it to make dummy text for Letraset's Body Type sheets. It
          has survived not only many decades, but also the leap into electronic
          typesetting, remaining essentially unchanged. It was popularised
          thanks to these sheets and more recently with desktop publishing
          software including versions of Lorem Ipsum.
        </p>
      </div>
    )
  }

  if (!description) {
    return (
      <div {...blockProps}>
        <Notice status='info' isDismissible={false}>
          {__('No description set for this post.', 'work')}
        </Notice>
      </div>
    )
  }

  return (
    <div {...blockProps}>
      <RawHTML>{safeHTML(autop(description))}</RawHTML>
    </div>
  )
}
