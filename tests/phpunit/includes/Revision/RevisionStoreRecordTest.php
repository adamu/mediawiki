<?php

namespace MediaWiki\Tests\Revision;

use CommentStoreComment;
use InvalidArgumentException;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionSlots;
use MediaWiki\Revision\RevisionStoreRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use stdClass;
use TextContent;
use Title;
use TitleValue;
use Wikimedia\Assert\PreconditionException;

/**
 * @covers \MediaWiki\Revision\RevisionStoreRecord
 * @covers \MediaWiki\Revision\RevisionRecord
 */
class RevisionStoreRecordTest extends MediaWikiIntegrationTestCase {

	public function provideConstructor() {
		$user = new UserIdentityValue( 11, 'Tester', 0 );
		$comment = CommentStoreComment::newUnsavedComment( 'Hello World' );

		$main = SlotRecord::newUnsaved( SlotRecord::MAIN, new TextContent( 'Lorem Ipsum' ) );
		$aux = SlotRecord::newUnsaved( 'aux', new TextContent( 'Frumious Bandersnatch' ) );
		$slots = new RevisionSlots( [ $main, $aux ] );

		$protoRow = [
			'rev_id' => '7',
			'rev_page' => '17',
			'rev_timestamp' => '20200101000000',
			'rev_deleted' => 0,
			'rev_minor_edit' => 0,
			'rev_parent_id' => '5',
			'rev_len' => $slots->computeSize(),
			'rev_sha1' => $slots->computeSha1(),
			'page_latest' => '18',
		];

		$row = $protoRow;
		yield 'all info' => [
			new PageIdentityValue( 17, NS_MAIN, 'Dummy', 'acmewiki' ),
			$user,
			$comment,
			(object)$row,
			$slots,
			'acmewiki',
			PreconditionException::class
		];

		yield 'all info, local' => [
			new PageIdentityValue( 17, NS_MAIN, 'Dummy', PageIdentity::LOCAL ),
			$user,
			$comment,
			(object)$row,
			$slots,
		];

		$title = Title::newFromText( 'Dummy' );
		$title->resetArticleID( 17 );

		// This case exists for b/c and should be deprecated.
		yield 'all info, foreign with Title' => [
			$title,
			$user,
			$comment,
			(object)$row,
			$slots,
			'acmewiki'
		];

		yield 'all info, local with Title' => [
			$title,
			$user,
			$comment,
			(object)$row,
			$slots,
		];

		$row = $protoRow;
		$row['rev_minor_edit'] = '1';
		$row['rev_deleted'] = strval( RevisionRecord::DELETED_USER );

		yield 'minor deleted' => [
			$title,
			$user,
			$comment,
			(object)$row,
			$slots
		];

		$row = $protoRow;
		$row['page_latest'] = $row['rev_id'];

		yield 'latest' => [
			$title,
			$user,
			$comment,
			(object)$row,
			$slots
		];

		$row = $protoRow;
		unset( $row['rev_parent'] );

		yield 'no parent' => [
			$title,
			$user,
			$comment,
			(object)$row,
			$slots
		];

		$row = $protoRow;
		$row['rev_len'] = null;
		$row['rev_sha1'] = '';

		yield 'rev_len is null, rev_sha1 is ""' => [
			$title,
			$user,
			$comment,
			(object)$row,
			$slots
		];

		$row = $protoRow;
		yield 'no length, no hash' => [
			Title::newFromText( 'DummyDoesNotExist' ),
			$user,
			$comment,
			(object)$row,
			$slots
		];
	}

	/**
	 * @dataProvider provideConstructor
	 *
	 * @param PageIdentity $page
	 * @param UserIdentity $user
	 * @param CommentStoreComment $comment
	 * @param stdClass $row
	 * @param RevisionSlots $slots
	 * @param bool $wikiId
	 * @param string|null $expectedException
	 */
	public function testConstructorAndGetters(
		PageIdentity $page,
		UserIdentity $user,
		CommentStoreComment $comment,
		$row,
		RevisionSlots $slots,
		$wikiId = RevisionRecord::LOCAL,
		string $expectedException = null
	) {
		$rec = new RevisionStoreRecord( $page, $user, $comment, $row, $slots, $wikiId );

		$this->assertTrue( $page->isSamePageAs( $rec->getPage() ), 'getPage' );
		$this->assertSame( $user, $rec->getUser( RevisionRecord::RAW ), 'getUser' );
		$this->assertSame( $comment, $rec->getComment(), 'getComment' );

		$this->assertSame( $slots, $rec->getSlots(), 'getSlots' );
		$this->assertSame( $slots->getSlotRoles(), $rec->getSlotRoles(), 'getSlotRoles' );
		$this->assertSame( $slots->getSlots(), $rec->getSlots()->getSlots(), 'getSlots' );
		$this->assertSame( $wikiId, $rec->getWikiId(), 'getWikiId' );

		$this->assertSame( (int)$row->rev_id, $rec->getId( $wikiId ), 'getId' );
		$this->assertSame( (int)$row->rev_page, $rec->getPageId(), 'getId' );
		$this->assertSame( $row->rev_timestamp, $rec->getTimestamp(), 'getTimestamp' );
		$this->assertSame( (int)$row->rev_deleted, $rec->getVisibility(), 'getVisibility' );
		$this->assertSame( (bool)$row->rev_minor_edit, $rec->isMinor(), 'getIsMinor' );

		if ( isset( $row->rev_parent_id ) ) {
			$this->assertSame( (int)$row->rev_parent_id, $rec->getParentId(), 'getParentId' );
		} else {
			$this->assertSame( 0, $rec->getParentId(), 'getParentId' );
		}

		if ( isset( $row->rev_len ) ) {
			$this->assertSame( (int)$row->rev_len, $rec->getSize(), 'getSize' );
		} else {
			$this->assertSame( $slots->computeSize(), $rec->getSize(), 'getSize' );
		}

		if ( !empty( $row->rev_sha1 ) ) {
			$this->assertSame( $row->rev_sha1, $rec->getSha1(), 'getSha1' );
		} else {
			$this->assertSame( $slots->computeSha1(), $rec->getSha1(), 'getSha1' );
		}

		if ( isset( $row->page_latest ) ) {
			$this->assertSame(
				(int)$row->rev_id === (int)$row->page_latest,
				$rec->isCurrent(),
				'isCurrent'
			);
		} else {
			$this->assertSame(
				false,
				$rec->isCurrent(),
				'isCurrent'
			);
		}

		if ( $expectedException ) {
			$this->expectException( $expectedException );
			$rec->getPageAsLinkTarget();
		} else {
			$this->assertTrue(
				TitleValue::newFromPage( $page )->isSameLinkAs( $rec->getPageAsLinkTarget() ),
				'getPageAsLinkTarget'
			);
		}
	}

	public function provideConstructorFailure() {
		$title = Title::newFromText( 'Dummy' );
		$title->resetArticleID( 17 );

		$user = new UserIdentityValue( 11, 'Tester', 0 );

		$comment = CommentStoreComment::newUnsavedComment( 'Hello World' );

		$main = SlotRecord::newUnsaved( SlotRecord::MAIN, new TextContent( 'Lorem Ipsum' ) );
		$aux = SlotRecord::newUnsaved( 'aux', new TextContent( 'Frumious Bandersnatch' ) );
		$slots = new RevisionSlots( [ $main, $aux ] );

		$protoRow = [
			'rev_id' => '7',
			'rev_page' => strval( $title->getArticleID() ),
			'rev_timestamp' => '20200101000000',
			'rev_deleted' => 0,
			'rev_minor_edit' => 0,
			'rev_parent_id' => '5',
			'rev_len' => $slots->computeSize(),
			'rev_sha1' => $slots->computeSha1(),
			'page_latest' => '18',
		];

		yield 'not a row' => [
			new PageIdentityValue( 17, NS_MAIN, 'Dummy', 'acmewiki' ),
			$user,
			$comment,
			'not a row',
			$slots,
			'acmewiki'
		];

		yield 'wiki mismatch' => [
			$title,
			$user,
			$comment,
			'not a row',
			$slots,
			'acmewiki'
		];

		$row = $protoRow;
		$row['rev_timestamp'] = 'kittens';

		yield 'bad timestamp' => [
			$title,
			$user,
			$comment,
			(object)$row,
			$slots
		];

		$row = $protoRow;
		$row['rev_page'] = 99;

		yield 'page ID mismatch' => [
			$title,
			$user,
			$comment,
			(object)$row,
			$slots
		];

		$row = $protoRow;

		yield 'bad wiki' => [
			$title,
			$user,
			$comment,
			(object)$row,
			$slots,
			12345
		];
	}

	/**
	 * @dataProvider provideConstructorFailure
	 *
	 * @param PageIdentity $page
	 * @param UserIdentity $user
	 * @param CommentStoreComment $comment
	 * @param stdClass $row
	 * @param RevisionSlots $slots
	 * @param bool $wikiId
	 */
	public function testConstructorFailure(
		PageIdentity $page,
		UserIdentity $user,
		CommentStoreComment $comment,
		$row,
		RevisionSlots $slots,
		$wikiId = false
	) {
		$this->expectException( InvalidArgumentException::class );
		new RevisionStoreRecord( $page, $user, $comment, $row, $slots, $wikiId );
	}
}
